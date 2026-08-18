<?php

namespace App\Models;

use App\Enums\AmountSource;
use App\Enums\WeighEntryMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * One reading taken off the counter scale, as it was keyed in.
 *
 * IMMUTABLE except for the void fields. A weighing is a record of what a
 * person saw on a display at a moment in time — editing it in place would
 * quietly rewrite the customer's bill with no trace that it ever said
 * something else. Corrections go through {@see supersede()}, which writes
 * a fresh row at revision+1; the old row stays exactly as it was.
 *
 * The `saving` guard below enforces that at the model level rather than by
 * convention, because "everyone remembers to use supersede()" is not a
 * property a bill can be audited against.
 */
class OrderItemWeighing extends Model
{
    protected $fillable = [
        'order_item_id',
        'net_grams',
        'amount_charged',
        'amount_source',
        'reference_price_per_kilo',
        'computed_amount',
        'variance_amount',
        'variance_percent',
        'variance_reason',
        'pieces',
        'cooking_style_id',
        'cooking_note',
        'entry_mode',
        'weighed_by_user_id',
        'weighed_at',
        'revision',
        'supersedes_id',
    ];

    /** Only these may change after the row exists. */
    protected const VOID_FIELDS = ['voided_at', 'voided_by_user_id', 'void_reason', 'updated_at'];

    protected function casts(): array
    {
        return [
            'net_grams' => 'integer',
            'amount_charged' => 'decimal:2',
            'amount_source' => AmountSource::class,
            'reference_price_per_kilo' => 'decimal:2',
            'computed_amount' => 'decimal:2',
            'variance_amount' => 'decimal:2',
            'variance_percent' => 'decimal:2',
            'pieces' => 'integer',
            'entry_mode' => WeighEntryMode::class,
            'weighed_at' => 'datetime',
            'revision' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (OrderItemWeighing $weighing) {
            $touched = array_diff(array_keys($weighing->getDirty()), self::VOID_FIELDS);

            if ($touched !== []) {
                throw new RuntimeException(
                    'A weighing is immutable — write a new revision with supersede() instead of changing: '
                    .implode(', ', $touched)
                );
            }
        });
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function cookingStyle(): BelongsTo
    {
        return $this->belongsTo(CookingStyle::class);
    }

    public function weighedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'weighed_by_user_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    /** The row this one replaced, if it is a correction. */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /**
     * Was this amount keyed as something other than what the reference rate
     * implies? Used to decide whether the order screen shows the amber
     * "charged X · expected Y" line, which is never hidden once true.
     */
    public function hasVariance(): bool
    {
        return bccomp((string) $this->variance_amount, '0.00', 2) !== 0;
    }

    /**
     * The correction path: a NEW row carrying the new figures, pointing at
     * this one. Never call update() on a weighing to change a number.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function supersede(array $attributes): self
    {
        return static::create($attributes + [
            'order_item_id' => $this->order_item_id,
            'revision' => $this->revision + 1,
            'supersedes_id' => $this->id,
        ]);
    }

    public function void(string $reason, ?User $by = null): self
    {
        $this->forceFill([
            'voided_at' => now(),
            'voided_by_user_id' => $by?->id,
            'void_reason' => $reason,
        ])->save();

        return $this;
    }
}
