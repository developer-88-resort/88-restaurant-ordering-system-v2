<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\OrderItemAdjustmentReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A financial reversal on one order item ("void served meal"). The
 * original order_items row stays untouched forever — this row is the
 * negative line the receipt shows underneath it.
 */
class OrderItemAdjustment extends Model
{
    use LogsAuditActivity;

    protected $fillable = [
        'order_item_id',
        'order_id',
        'quantity',
        'unit_price',
        'reversed_amount',
        'reason_code',
        'notes',
        'inventory_restored',
        'requested_by',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'reversed_amount' => 'decimal:2',
            'reason_code' => OrderItemAdjustmentReason::class,
            'inventory_restored' => 'boolean',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected function auditLabel(): string
    {
        return 'Item Cancellation';
    }

    protected function auditIdentifier(): string
    {
        return ($this->orderItem?->item_name ?? 'item').' ×'.$this->quantity;
    }
}
