<?php

namespace App\Services;

use App\Enums\AmountSource;
use App\Enums\LineType;
use App\Enums\OrderItemConfirmationStatus;
use App\Enums\WeighEntryMode;
use App\Models\CookingStyle;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemWeighing;
use App\Models\User;
use App\Support\WeighAudit;
use App\Support\WeighedLinePricer;
use App\Support\WeighedOrderSettings;
use App\Support\WeighVarianceChecker;
use App\Support\WeighVarianceResult;
use Illuminate\Validation\ValidationException;

/**
 * Turning a scale reading into money on a bill.
 *
 * The counter scale supplies both numbers now — the weight and the amount
 * — and staff key in what the display shows. So this class does NOT decide
 * what to charge. It records what it was told, works out what the day's
 * reference rate implies, and refuses to let the two drift apart quietly:
 *
 *   amount_charged  ← the scale display, keyed by a person
 *   computed_amount ← WeighedLinePricer against the resolved reference rate
 *   line_total      ← amount_charged + cooking surcharge × pieces
 *
 * The surcharge is added on top and never folded into amount_charged: the
 * scale weighs fish, it knows nothing about what the kitchen charges to
 * grill it, so mixing the two would make the variance comparison
 * meaningless.
 *
 * Every path in here writes an immutable {@see OrderItemWeighing}. The
 * order_items row holds the line's current state; the weighings hold how
 * it got there.
 */
class WeighedLineRecorder
{
    /**
     * Record a fresh weighing and create the order line it produced.
     *
     * @param  array<string, mixed>  $data
     */
    public static function record(Order $order, MenuItem $menuItem, array $data, ?User $user = null): OrderItem
    {
        $style = self::resolveCookingStyle($menuItem, $data);
        $netGrams = (int) $data['net_grams'];
        $pieces = max(1, (int) ($data['pieces'] ?? 1));

        // Resolved here, never accepted from the client — otherwise the
        // daily market price page would be decorative and any caller could
        // price its own fish.
        $rate = self::resolveRate($menuItem);
        $computed = self::computedAmount($menuItem, $netGrams, $rate);

        $variance = self::assertExplained(
            WeighVarianceChecker::make()->check($computed, $data['amount_charged']),
            $data,
            $user,
        );

        $surcharge = WeighedLinePricer::surcharge((string) ($style->surcharge ?? '0'), $pieces);
        $lineTotal = bcadd($variance->amountCharged, $surcharge, 2);

        $item = $order->items()->create([
            'menu_item_id' => $menuItem->id,
            'menu_item_variant_id' => null,
            'item_name' => $menuItem->name,
            'unit_price' => $lineTotal,
            'quantity' => 1,
            'subtotal' => $lineTotal,
            'notes' => $data['notes'] ?? null,
            'line_type' => LineType::Weighed,
            'weight_grams' => $netGrams,
            // Deprecated: the scale's own TARE button already produced the
            // net figure, so the app never subtracts a second time.
            'tare_grams' => 0,
            'pieces' => $pieces,
            'price_per_kilo_snapshot' => $rate,
            'cooking_style_id' => $style?->id,
            'cooking_note' => $data['cooking_note'] ?? null,
            'weighed_by_user_id' => $user?->id,
            'weighed_at' => now(),
            'ordered_by_guest_id' => $data['ordered_by_guest_id'] ?? null,
            'batch_number' => $data['batch_number'] ?? null,
            'confirmation_status' => $data['confirmation_status'] ?? OrderItemConfirmationStatus::Confirmed->value,
            // Billed at the minimum rather than at what it actually weighs
            // — a manager should see that, not discover it on a complaint.
            'flagged_for_review' => self::isBelowMinimum($menuItem, $netGrams),
        ]);

        $weighing = OrderItemWeighing::create(self::weighingAttributes(
            $item, $netGrams, $rate, $computed, $variance, $pieces, $style, $data, $user
        ) + ['revision' => 1]);

        WeighAudit::recorded($item, $weighing->load('cookingStyle'), $order, $user);

        if ($variance->requiresReason) {
            WeighAudit::varianceFlagged($item, $weighing, $order, $user, $variance, $variance->requiresOverride);
        }

        return $item;
    }

    /**
     * Correct an existing weighed line.
     *
     * Writes revision N+1 and points the order line at it. The previous
     * revision stays exactly as it was — the customer may already have
     * seen it, and a bill that silently changed shape is the thing an
     * audit trail exists to make impossible.
     *
     * @param  array<string, mixed>  $data
     */
    public static function revise(OrderItem $item, array $data, ?User $user = null): OrderItem
    {
        $current = $item->activeWeighing();

        if (! $current) {
            throw ValidationException::withMessages([
                'weight' => __('This line has no weighing on record to correct.'),
            ]);
        }

        $menuItem = $item->menuItem;
        $style = $menuItem
            ? self::resolveCookingStyle($menuItem, $data + ['cooking_style_id' => $data['cooking_style_id'] ?? $item->cooking_style_id])
            : $item->cookingStyle;

        $netGrams = (int) $data['net_grams'];
        $pieces = max(1, (int) ($data['pieces'] ?? $item->pieces ?? 1));

        // Re-checked against the line's OWN frozen rate, not today's:
        // fixing a typo must not also move the line onto a rate it was
        // never sold at.
        $rate = (string) $current->reference_price_per_kilo;
        $computed = $menuItem
            ? self::computedAmount($menuItem, $netGrams, $rate)
            : WeighedLinePricer::base($netGrams, $rate);

        $variance = self::assertExplained(
            WeighVarianceChecker::make()->check($computed, $data['amount_charged']),
            $data,
            $user,
            // A correction always explains itself, whatever its size — the
            // reason is what tells a later reader why the bill moved.
            alwaysRequiresReason: true,
        );

        $surcharge = WeighedLinePricer::surcharge((string) ($style->surcharge ?? '0'), $pieces);
        $lineTotal = bcadd($variance->amountCharged, $surcharge, 2);

        $revision = $current->supersede(self::weighingAttributes(
            $item, $netGrams, $rate, $computed, $variance, $pieces, $style, $data, $user
        ));

        $item->update([
            'weight_grams' => $netGrams,
            'tare_grams' => 0,
            'pieces' => $pieces,
            'unit_price' => $lineTotal,
            'subtotal' => $lineTotal,
            'cooking_style_id' => $style?->id,
            'price_override_reason' => $data['reason'] ?? $data['variance_reason'] ?? null,
            'price_overridden_by_user_id' => $user?->id,
            'weighed_by_user_id' => $user?->id,
            'weighed_at' => now(),
            'flagged_for_review' => $menuItem ? self::isBelowMinimum($menuItem, $netGrams) : false,
        ]);

        $order = $item->order;

        WeighAudit::edited($item, $current, $revision, $order, $user);

        if ($variance->requiresReason) {
            WeighAudit::varianceFlagged($item, $revision, $order, $user, $variance, $variance->requiresOverride);
        }

        return $item;
    }

    /**
     * Void the active weighing behind a line. Soft only: the row stays,
     * carrying who voided it and why, and the order screen keeps showing
     * it struck through. Nothing about a bill is ever deleted.
     */
    public static function void(OrderItem $item, string $reason, ?User $user = null): void
    {
        $weighing = $item->activeWeighing();

        if (! $weighing) {
            return;
        }

        $weighing->void($reason, $user);

        WeighAudit::voided($item, $weighing, $item->order, $user, $reason);
    }

    /**
     * Today's market price if the manager set one, else the item's
     * standing reference rate.
     */
    protected static function resolveRate(MenuItem $menuItem): string
    {
        $rate = $menuItem->effectivePricePerKilo();

        if ($rate === null || bccomp((string) $rate, '0.00', 2) <= 0) {
            throw ValidationException::withMessages([
                'menu_item_id' => __(':item has no price per kilo set. Set one in Menu Management first.', [
                    'item' => $menuItem->name,
                ]),
            ]);
        }

        return (string) $rate;
    }

    /**
     * What the reference rate implies for this reading.
     *
     * Under "Bill at minimum" a short weight is priced as if it hit the
     * minimum, which is the whole meaning of that setting — the customer
     * keeps the small fish, the kitchen keeps its floor price.
     */
    protected static function computedAmount(MenuItem $menuItem, int $netGrams, string $rate): string
    {
        $chargeable = self::isBelowMinimum($menuItem, $netGrams)
            ? (int) $menuItem->min_weight_grams
            : $netGrams;

        return WeighedLinePricer::base($chargeable, $rate);
    }

    protected static function isBelowMinimum(MenuItem $menuItem, int $netGrams): bool
    {
        return $menuItem->isPerKilo()
            && $menuItem->min_weight_grams
            && $netGrams < (int) $menuItem->min_weight_grams
            && WeighedOrderSettings::current()->billsAtMinimum();
    }

    /**
     * Hold the keyed amount to the variance rules, or refuse the line.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function assertExplained(
        WeighVarianceResult $variance,
        array $data,
        ?User $user,
        bool $alwaysRequiresReason = false,
    ): WeighVarianceResult {
        $reason = trim((string) ($data['variance_reason'] ?? $data['reason'] ?? ''));

        if ($variance->requiresOverride && ! $user?->can('weigh.override_price')) {
            throw ValidationException::withMessages([
                'amount_charged' => __('Supervisor approval is required for a difference this large.'),
            ]);
        }

        if (($variance->requiresReason || $alwaysRequiresReason) && $reason === '') {
            throw ValidationException::withMessages([
                'variance_reason' => __(
                    'The scale says ₱:charged but ₱:expected was expected — a reason is required.',
                    ['charged' => $variance->amountCharged, 'expected' => $variance->computedAmount],
                ),
            ]);
        }

        return $variance;
    }

    /**
     * The columns every revision shares, so a correction is recorded in
     * exactly the same shape as the original reading.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function weighingAttributes(
        OrderItem $item,
        int $netGrams,
        string $rate,
        string $computed,
        WeighVarianceResult $variance,
        int $pieces,
        ?CookingStyle $style,
        array $data,
        ?User $user,
    ): array {
        $reason = trim((string) ($data['variance_reason'] ?? $data['reason'] ?? ''));

        return [
            'order_item_id' => $item->id,
            'net_grams' => $netGrams,
            'amount_charged' => $variance->amountCharged,
            'amount_source' => $data['amount_source'] ?? AmountSource::Typed->value,
            'reference_price_per_kilo' => $rate,
            'computed_amount' => $computed,
            'variance_amount' => $variance->varianceAmount,
            'variance_percent' => $variance->variancePercent,
            'variance_reason' => $reason !== '' ? $reason : null,
            'pieces' => $pieces,
            'cooking_style_id' => $style?->id,
            'cooking_note' => $data['cooking_note'] ?? $item->cooking_note,
            'entry_mode' => $data['entry_mode'] ?? WeighEntryMode::InPerson->value,
            'weighed_by_user_id' => $user?->id,
            'weighed_at' => now(),
        ];
    }

    /**
     * A cooking style must be one the item actually offers — otherwise a
     * client could attach a surcharged style the kitchen never agreed to
     * cook for that dish.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function resolveCookingStyle(MenuItem $menuItem, array $data): ?CookingStyle
    {
        if (empty($data['cooking_style_id'])) {
            return null;
        }

        $style = $menuItem->cookingStyles()->find($data['cooking_style_id']);

        if (! $style) {
            throw ValidationException::withMessages([
                'cooking_style_id' => __('That cooking style is not offered for :item.', ['item' => $menuItem->name]),
            ]);
        }

        return $style;
    }
}
