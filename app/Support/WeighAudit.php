<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemWeighing;
use App\Models\User;

/**
 * The audit trail for weighed lines.
 *
 * Every entry here carries the same spine — who, when, which order, which
 * table — because a weighing that can't be tied back to a bill and a
 * person is not evidence of anything. Each action gets its own event name
 * so the Audit Logs page can filter to it; a weigh-in must never surface
 * as a generic "Order Updated" among a hundred unrelated rows.
 *
 * Kept out of the recorder so the recorder reads as pricing logic and the
 * shape of an audit entry is defined in exactly one place.
 */
class WeighAudit
{
    public const RECORDED = 'weigh_in_recorded';

    public const VARIANCE_FLAGGED = 'weigh_variance_flagged';

    public const EDITED = 'weigh_in_edited';

    public const VOIDED = 'weigh_in_voided';

    public const LINE_APPENDED = 'order_line_appended';

    /**
     * All five weighed-line events, for the Audit Logs filter.
     *
     * @return array<string, string>
     */
    public static function events(): array
    {
        return [
            self::RECORDED => __('Weigh-in recorded'),
            self::VARIANCE_FLAGGED => __('Weigh variance flagged'),
            self::EDITED => __('Weigh-in edited'),
            self::VOIDED => __('Weigh-in voided'),
            self::LINE_APPENDED => __('Line added to order'),
        ];
    }

    public static function recorded(OrderItem $item, OrderItemWeighing $weighing, Order $order, ?User $by): void
    {
        self::write($order, $item, $by, self::RECORDED, [
            'net_grams' => $weighing->net_grams,
            'pieces' => $weighing->pieces,
            'reference_price_per_kilo' => (string) $weighing->reference_price_per_kilo,
            'amount_charged' => (string) $weighing->amount_charged,
            'computed_amount' => (string) $weighing->computed_amount,
            'variance_amount' => (string) $weighing->variance_amount,
            'cooking_style' => $weighing->cookingStyle?->name,
            'cooking_note' => $weighing->cooking_note,
            'entry_mode' => $weighing->entry_mode->value,
        ], sprintf(
            'WEIGH-IN RECORDED: %s — %d g @ ₱%s/kg, charged ₱%s (expected ₱%s) on order %s',
            $item->item_name,
            $weighing->net_grams,
            $weighing->reference_price_per_kilo,
            $weighing->amount_charged,
            $weighing->computed_amount,
            $order->orderNumber(),
        ));
    }

    public static function varianceFlagged(
        OrderItem $item,
        OrderItemWeighing $weighing,
        Order $order,
        ?User $by,
        WeighVarianceResult $variance,
        bool $usedOverride,
    ): void {
        self::write($order, $item, $by, self::VARIANCE_FLAGGED, [
            'expected' => $variance->computedAmount,
            'charged' => $variance->amountCharged,
            'variance_amount' => $variance->varianceAmount,
            'variance_percent' => $variance->variancePercent,
            'tolerance' => $variance->tolerance,
            'reason' => $weighing->variance_reason,
            'override_permission_used' => $usedOverride,
        ], sprintf(
            'WEIGH VARIANCE FLAGGED: %s — charged ₱%s vs expected ₱%s (%s%%)%s. Reason: %s',
            $item->item_name,
            $variance->amountCharged,
            $variance->computedAmount,
            $variance->variancePercent,
            $usedOverride ? ' [supervisor override]' : '',
            $weighing->variance_reason ?: __('none given'),
        ));
    }

    public static function edited(
        OrderItem $item,
        OrderItemWeighing $old,
        OrderItemWeighing $new,
        Order $order,
        ?User $by,
    ): void {
        self::write($order, $item, $by, self::EDITED, [
            'revision' => $old->revision.' -> '.$new->revision,
            'old_net_grams' => $old->net_grams,
            'old_amount_charged' => (string) $old->amount_charged,
            'new_net_grams' => $new->net_grams,
            'new_amount_charged' => (string) $new->amount_charged,
            'reason' => $new->variance_reason,
        ], sprintf(
            'WEIGH-IN EDITED: %s rev %d → %d — %d g / ₱%s → %d g / ₱%s on order %s. Reason: %s',
            $item->item_name,
            $old->revision,
            $new->revision,
            $old->net_grams,
            $old->amount_charged,
            $new->net_grams,
            $new->amount_charged,
            $order->orderNumber(),
            $new->variance_reason ?: __('none given'),
        ));
    }

    public static function voided(OrderItem $item, OrderItemWeighing $weighing, Order $order, ?User $by, string $reason): void
    {
        self::write($order, $item, $by, self::VOIDED, [
            'net_grams' => $weighing->net_grams,
            'amount_charged' => (string) $weighing->amount_charged,
            'revision' => $weighing->revision,
            'reason' => $reason,
        ], sprintf(
            'WEIGH-IN VOIDED: %s — %d g / ₱%s on order %s. Reason: %s',
            $item->item_name,
            $weighing->net_grams,
            $weighing->amount_charged,
            $order->orderNumber(),
            $reason,
        ));
    }

    public static function lineAppended(OrderItem $item, Order $order, ?User $by): void
    {
        self::write($order, $item, $by, self::LINE_APPENDED, [
            'item' => $item->item_name,
            'quantity' => $item->quantity,
            'subtotal' => (string) $item->subtotal,
        ], sprintf(
            'ORDER LINE APPENDED: %d× %s (₱%s) to order %s',
            $item->quantity,
            $item->item_name,
            $item->subtotal,
            $order->orderNumber(),
        ));
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected static function write(Order $order, OrderItem $item, ?User $by, string $event, array $properties, string $message): void
    {
        activity('audit')
            ->causedBy($by)
            ->performedOn($item)
            ->event($event)
            ->withProperties($properties + [
                'order_number' => $order->orderNumber(),
                'order_id' => $order->id,
                'table' => $order->space?->name,
                'item' => $item->item_name,
            ])
            ->log($message);
    }
}
