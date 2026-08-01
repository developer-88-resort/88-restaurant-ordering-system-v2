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
     * What this weighed line costs, computed by the one shared pricer from
     * the line's own frozen snapshot rate. Returns null when the line is
     * not weighed or has not been put on the scale yet.
     */
    public function weighedTotal(): ?string
    {
        if (! $this->isWeighed() || $this->weight_grams === null) {
            return null;
        }

        return WeighedLinePricer::total(
            weightGrams: $this->weight_grams,
            pricePerKilo: (string) $this->price_per_kilo_snapshot,
            tareGrams: (int) $this->tare_grams,
            surchargePerPiece: (string) ($this->cookingStyle->surcharge ?? '0'),
            pieces: $this->pieces,
        );
    }

    /**
     * Scale detail for receipts and the order screen, e.g.
     * "1,250 g @ ₱480.00/kg" — with the gross/tare breakdown appended when
     * a tare was actually deducted, so the customer can see the arithmetic
     * rather than being asked to trust the net figure.
     */
    public function weightLabel(): ?string
    {
        if (! $this->isWeighed() || $this->weight_grams === null) {
            return null;
        }

        $label = number_format((float) $this->netWeightGrams()).' g @ ₱'
            .number_format((float) $this->price_per_kilo_snapshot, 2).'/kg';

        if ((int) $this->tare_grams > 0) {
            $label .= ' ('.number_format((float) $this->weight_grams).' g − '
                .number_format((float) $this->tare_grams).' g '.__('tare').')';
        }

        return $label;
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
