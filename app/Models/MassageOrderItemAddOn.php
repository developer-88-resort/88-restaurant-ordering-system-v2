<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An add-on on a massage order line, copied at the time of the order.
 * Quantity is the total for the line (per massage × the line's quantity).
 */
class MassageOrderItemAddOn extends Model
{
    protected $fillable = [
        'massage_order_item_id',
        'massage_service_add_on_id',
        'name',
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

    public function item(): BelongsTo
    {
        return $this->belongsTo(MassageOrderItem::class, 'massage_order_item_id');
    }
}
