<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\OrderItemAdjustmentReason;
use App\Enums\OrderItemAdjustmentSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Contracts\Activity;

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
        'source',
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
            'source' => OrderItemAdjustmentSource::class,
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

    /**
     * A cancellation gets its own event name and a sentence that answers
     * what, where, why and who in one read — it moves money on a bill, so
     * it must not sit on the Audit Logs page as a generic "Created" row.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($eventName !== 'created') {
            return;
        }

        $this->loadMissing(['orderItem', 'order', 'approvedBy']);

        $activity->event = 'order_item_cancelled';
        $activity->description = sprintf(
            'ITEM CANCELLED (%s): %d× %s on order %s — %s%s%s',
            $this->source?->label() ?? __('Order Management'),
            $this->quantity,
            $this->orderItem?->item_name ?? 'item',
            $this->order?->orderNumber() ?? '#'.$this->order_id,
            $this->reason_code->label(),
            $this->notes ? ' ('.$this->notes.')' : '',
            $this->approvedBy && $this->approved_by !== $this->requested_by
                ? '. Approved by '.$this->approvedBy->name
                : '',
        );
    }
}
