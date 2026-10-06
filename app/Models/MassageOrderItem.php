<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A service on a massage order. Name, variant, price and add-ons are copied
 * at the time of the order, so editing or archiving the service later never
 * changes it. The subtotal includes the line's add-ons.
 */
class MassageOrderItem extends Model
{
    protected $fillable = [
        'massage_order_id',
        'massage_service_id',
        'massage_service_variant_id',
        'name',
        'variant_name',
        'unit_price',
        'quantity',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'subtotal' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MassageOrder::class, 'massage_order_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(MassageService::class, 'massage_service_id')->withTrashed();
    }

    public function addOns(): HasMany
    {
        return $this->hasMany(MassageOrderItemAddOn::class);
    }

    /** "SWEDISH MASSAGE — 1 hr" */
    public function label(): string
    {
        return $this->variant_name ? $this->name.' — '.$this->variant_name : $this->name;
    }
}
