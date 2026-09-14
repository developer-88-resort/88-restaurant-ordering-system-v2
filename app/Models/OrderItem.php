<?php

namespace App\Models;

use App\Enums\LineType;
use App\Enums\OrderItemConfirmationStatus;
use App\Support\WeighedLinePricer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'menu_item_id',
        'menu_item_variant_id',
        'menu_item_add_on_id',
        'parent_order_item_id',
        'item_name',
        'unit_price',
        'quantity',
        'subtotal',
        'notes',
        'is_discount_eligible',
        'line_type',
        'weight_grams',
        'tare_grams',
        'pieces',
        'price_per_kilo_snapshot',
        'cooking_style_id',
        'cooking_note',
        'weighed_by_user_id',
        'weighed_at',
        'price_override_reason',
        'price_overridden_by_user_id',
        'ordered_by_guest_id',
        'confirmation_status',
        'flagged_for_review',
        'batch_number',
        'scheduled_for',
        'quotation_id',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'is_discount_eligible' => 'boolean',
            'line_type' => LineType::class,
            'weight_grams' => 'integer',
            'tare_grams' => 'integer',
            'pieces' => 'integer',
            'price_per_kilo_snapshot' => 'decimal:2',
            'weighed_at' => 'datetime',
            'confirmation_status' => OrderItemConfirmationStatus::class,
            'flagged_for_review' => 'boolean',
            'scheduled_for' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function menuItemVariant(): BelongsTo
    {
        return $this->belongsTo(MenuItemVariant::class);
    }

    public function menuItemAddOn(): BelongsTo
    {
        return $this->belongsTo(MenuItemAddOn::class);
    }

    /**
     * The line this add-on row was chosen alongside — null for an ordinary
     * (non-add-on) line. See parent_order_item_id: an add-on is its own
     * sibling OrderItem row (not nested JSON on the parent) so the kitchen
     * board, receipts, and reports need zero changes to show it.
     */
    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_order_item_id');
    }

    /**
     * The add-on rows chosen alongside this line, if this line is itself a
     * parent (not an add-on).
     */
    public function addOnLines(): HasMany
    {
        return $this->hasMany(self::class, 'parent_order_item_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(OrderItemAdjustment::class);
    }

    public function cookingStyle(): BelongsTo
    {
        return $this->belongsTo(CookingStyle::class);
    }

    public function weighedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'weighed_by_user_id');
    }

    public function priceOverriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'price_overridden_by_user_id');
    }

    public function orderedByGuest(): BelongsTo
    {
        return $this->belongsTo(GuestSession::class, 'ordered_by_guest_id');
    }

    /**
     * Every reading ever taken for this line, newest revision first. The
     * older ones are history, never overwritten.
     */
    public function weighings(): HasMany
    {
        return $this->hasMany(OrderItemWeighing::class)->orderByDesc('revision');
    }

    /**
     * The reading this line currently stands on: the highest revision that
     * has not been voided.
     */
    public function activeWeighing(): ?OrderItemWeighing
    {
        if ($this->relationLoaded('weighings')) {
            return $this->weighings->firstWhere('voided_at', null);
        }

        return $this->weighings()->active()->first();
    }

    public function isWeighed(): bool
    {
        return $this->line_type === LineType::Weighed;
    }

    /**
     * Net grams being charged on this line (gross minus tare).
     */
    public function netWeightGrams(): ?int
    {
        if (! $this->isWeighed() || $this->weight_grams === null) {
            return null;
        }

        return WeighedLinePricer::netGrams($this->weight_grams, (int) $this->tare_grams);
    }

    /**
     * The weight charge alone, before cooking.
     *
     * For a line recorded at the counter this is what the scale display
     * said — not a figure this app derived. Legacy lines predating the
     * weighings table fall back to the pricer against their frozen rate,
     * which is how they were billed at the time.
     */
    public function amountCharged(): ?string
    {
        if (! $this->isWeighed() || $this->weight_grams === null) {
            return null;
        }

        if ($weighing = $this->activeWeighing()) {
            return (string) $weighing->amount_charged;
        }

        return WeighedLinePricer::base(
            weightGrams: $this->weight_grams,
            pricePerKilo: (string) $this->price_per_kilo_snapshot,
            tareGrams: (int) $this->tare_grams,
        );
    }

    /**
     * Cooking surcharge for this line, charged per piece. Always shown as
     * its own sub-line: the scale weighs fish and knows nothing about what
     * the kitchen charges to grill it, so the two must never be folded
     * into one number.
     */
    public function cookingSurcharge(): string
    {
        return WeighedLinePricer::surcharge(
            (string) ($this->cookingStyle->surcharge ?? '0'),
            $this->pieces,
        );
    }

    /**
     * What this weighed line costs in total: the amount off the scale plus
     * the cooking surcharge. Returns null when the line is not weighed or
     * has not been put on the scale yet.
     */
    public function weighedTotal(): ?string
    {
        $charged = $this->amountCharged();

        return $charged === null ? null : bcadd($charged, $this->cookingSurcharge(), 2);
    }

    /**
     * Scale detail for receipts and the order screen, e.g.
     * "0.600 kg @ ₱295.00/kg". Kilos rather than grams because that is
     * what the customer sees on the counter display and what the market
     * rate is quoted in.
     */
    public function weightLabel(): ?string
    {
        if (! $this->isWeighed() || $this->weight_grams === null) {
            return null;
        }

        $label = number_format((float) $this->netWeightGrams() / 1000, 3).' '.__('kg').' @ ₱'
            .number_format((float) $this->price_per_kilo_snapshot, 2).'/kg';

        // Historical lines only: the app no longer deducts a tare, but
        // rows that carry one must still show the arithmetic they were
        // billed on rather than a net figure with no explanation.
        if ((int) $this->tare_grams > 0) {
            $label .= ' ('.number_format((float) $this->weight_grams).' g − '
                .number_format((float) $this->tare_grams).' g '.__('tare').')';
        }

        return $label;
    }

    /**
     * "Charged ₱500.00 · expected ₱177.00 · Reason: …" — shown whenever
     * the keyed amount differs from what the reference rate implies, and
     * never hidden once it does. A bill that moved for a reason has to
     * carry that reason where the person paying it can see it.
     */
    public function varianceLabel(): ?string
    {
        $weighing = $this->activeWeighing();

        if (! $weighing || ! $weighing->hasVariance()) {
            return null;
        }

        $label = __('Charged ₱:charged · expected ₱:expected', [
            'charged' => number_format((float) $weighing->amount_charged, 2),
            'expected' => number_format((float) $weighing->computed_amount, 2),
        ]);

        if ($weighing->variance_reason) {
            $label .= ' · '.__('Reason').': '.$weighing->variance_reason;
        }

        return $label;
    }

    /**
     * "In person · Maria Cruz · 2:14 PM" — how the numbers on this line
     * got here.
     */
    public function weighProvenanceLabel(): ?string
    {
        $weighing = $this->activeWeighing();

        if (! $weighing) {
            return null;
        }

        return collect([
            $weighing->entry_mode->label(),
            $weighing->weighedBy?->name,
            $weighing->weighed_at?->format('g:i A'),
        ])->filter()->implode(' · ');
    }

    /**
     * "Inihaw ×2" — the cooking style plus the piece count it is charged
     * against, shown only when there is something to say.
     */
    public function cookingLabel(): ?string
    {
        if (! $this->isWeighed()) {
            return null;
        }

        $style = $this->cookingStyle?->name;
        $pieces = (int) ($this->pieces ?? 0);

        if (! $style) {
            return $pieces > 1 ? $pieces.' '.__('pcs') : null;
        }

        return $pieces > 1 ? $style.' ×'.$pieces : $style;
    }

    public function cancelledQuantity(): int
    {
        return (int) $this->adjustments->sum('quantity');
    }

    public function activeQuantity(): int
    {
        return max(0, $this->quantity - $this->cancelledQuantity());
    }

    public function isFullyCancelled(): bool
    {
        return $this->activeQuantity() === 0;
    }

    public function reversedAmount(): string
    {
        $total = '0.00';
        foreach ($this->adjustments as $adjustment) {
            $total = bcadd($total, (string) $adjustment->reversed_amount, 2);
        }

        return $total;
    }

    /**
     * This line's current net charge: base subtotal − cancellation
     * reversals, never below zero. Order::recalculateTotal() sums exactly
     * this across all lines.
     */
    public function lineTotalNet(): string
    {
        $net = bcsub((string) $this->subtotal, $this->reversedAmount(), 2);

        return bccomp($net, '0.00', 2) < 0 ? '0.00' : $net;
    }
}
