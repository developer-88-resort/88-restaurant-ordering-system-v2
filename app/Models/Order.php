<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Contracts\Activity;

class Order extends Model
{
    use LogsAuditActivity;

    protected $fillable = [
        'order_number',
        'order_type',
        'area_id',
        'space_category_id',
        'space_id',
        'space_session_id',
        'created_by',
        'status',
        'payment_status',
        'payment_method',
        'payment_reference',
        'receipt_number',
        'current_invoice_snapshot_id',
        'amount_received',
        'change_amount',
        'voided_by',
        'voided_at',
        'void_reason',
        'total_amount',
        'notes',
        'customer_name',
        'covers_count',
        'paid_at',
        'guest_session_id',
        'batch_number',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'order_type' => OrderType::class,
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'total_amount' => 'decimal:2',
            'amount_received' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->public_token ??= Str::random(32);
        });
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function spaceCategory(): BelongsTo
    {
        return $this->belongsTo(SpaceCategory::class, 'space_category_id');
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function spaceSession(): BelongsTo
    {
        return $this->belongsTo(SpaceSession::class);
    }

    public function locationLabel(): string
    {
        if ($this->order_type === OrderType::Takeout) {
            return 'Take-out';
        }

        if (! $this->area || ! $this->spaceCategory) {
            return 'Unknown';
        }

        return $this->area->name.' - '.($this->space->name ?? $this->spaceCategory->name);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function guestSession(): BelongsTo
    {
        return $this->belongsTo(GuestSession::class);
    }

    /**
     * The advance-order quotation this order was converted from, when it
     * originated as one — used to label the order/receipt accordingly.
     */
    public function sourceQuotation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Quotation::class, 'converted_order_id');
    }

    /**
     * Every payment entry ever recorded on this order, including voided
     * ones — split payments are several rows here.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    /**
     * Item-level cancellations/void reversals across the whole order
     * (denormalized order_id on the adjustment makes this a direct query).
     */
    public function itemAdjustments(): HasMany
    {
        return $this->hasMany(OrderItemAdjustment::class);
    }

    /**
     * Sum of all still-valid (non-voided) payment entries.
     */
    public function paidAmount(): string
    {
        return (string) $this->payments
            ->where('status', \App\Enums\OrderPaymentStatus::Recorded)
            ->sum(fn (OrderPayment $payment) => (float) $payment->amount);
    }

    /**
     * Recompute total_amount as the authoritative sum of every line's net
     * charge: base subtotal − cancelled reversals (each line clamped at
     * zero). Called after any item cancellation — never trusts a
     * client-sent total.
     */
    public function recalculateTotal(): void
    {
        $this->loadMissing(['items.adjustments']);

        $total = '0.00';
        foreach ($this->items as $item) {
            $total = bcadd($total, $item->lineTotalNet(), 2);
        }

        $this->update(['total_amount' => $total]);
    }

    /**
     * Every invoice ever issued for this order, including ones later
     * voided — permanent record, never mutated or deleted.
     */
    public function invoiceSnapshots(): HasMany
    {
        return $this->hasMany(OrderInvoiceSnapshot::class);
    }

    /**
     * The currently-active invoice (or the most recent one, if voided and
     * not yet repaid) for display — set atomically at finalize-payment
     * time via `current_invoice_snapshot_id`, so this is a direct indexed
     * lookup rather than string-matching against receipt_number.
     */
    public function currentInvoiceSnapshot(): BelongsTo
    {
        return $this->belongsTo(OrderInvoiceSnapshot::class, 'current_invoice_snapshot_id');
    }

    /**
     * The customer/staff-facing order number for display, e.g.
     * "#88-0711-001". The stored value (`order_number`) has no "#" — this
     * is the one place that adds it, so every view stays consistent
     * automatically.
     */
    public function orderNumber(): string
    {
        return '#'.$this->order_number;
    }

    protected function auditIdentifier(): string
    {
        return $this->order_number;
    }

    /**
     * Give status/payment changes a specific, readable description instead
     * of the generic "Updated Order: ORD-..." — these are the two fields
     * staff most need to see at a glance in the audit trail.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($eventName !== 'updated') {
            return;
        }

        $parts = [];

        if ($this->wasChanged('status')) {
            $parts[] = "status changed to {$this->status->label()}";
        }

        if ($this->wasChanged('payment_status')) {
            $parts[] = "payment marked as {$this->payment_status->label()}";

            if ($this->payment_status === PaymentStatus::Paid && $this->currentInvoiceSnapshot?->discount_type) {
                $parts[] = "{$this->currentInvoiceSnapshot->discount_type->label()} discount applied (₱{$this->currentInvoiceSnapshot->discount_amount})";
            }

            if ($this->payment_status === PaymentStatus::Voided && $this->receipt_number) {
                $parts[] = "invoice {$this->receipt_number} voided";
            }
        }

        if ($parts !== []) {
            $activity->description = "Order {$this->order_number}: ".implode(' & ', $parts).'.';
        }
    }
}
