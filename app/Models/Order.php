<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
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
        'pax',
        'area_id',
        'space_category_id',
        'space_id',
        'space_session_id',
        'slip_number',
        'created_by',
        'order_source',
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
            'order_source' => OrderSource::class,
            'slip_number' => 'integer',
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

            // Every order that joins a table's tab is that tab's next slip.
            // Numbered here rather than by each caller so no path that
            // creates an order (staff New Order, QR, Weigh, Quotation) can
            // forget it — see SpaceSession::nextSlipNumber() for the lock.
            if ($order->space_session_id && $order->slip_number === null) {
                $order->slip_number = SpaceSession::nextSlipNumber($order->space_session_id);
            }
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

    /**
     * "Slip #2" — the order's place within its table's tab, or null for an
     * order that isn't on a tab (take-out, older records).
     */
    public function slipLabel(): ?string
    {
        return $this->slip_number ? __('Slip #:number', ['number' => $this->slip_number]) : null;
    }

    /**
     * "Cottages - Cottage 3 — Slip #2": how the kitchen and Order Management
     * tell apart several slips open for the same table.
     */
    public function slipLocationLabel(): string
    {
        $slip = $this->slipLabel();

        return $slip ? $this->locationLabel().' — '.$slip : $this->locationLabel();
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

    /**
     * Leaves out advance orders that are still for later — a reservation
     * isn't at the table yet, so it must not show up as one of the table's
     * open slips (and today's walk-in must never be added to it).
     *
     * An order only counts as one when EVERY line on it is scheduled for
     * later. Matching on "has a quotation scheduled for later" alone hid a
     * normal, already-cooking slip the moment a later advance order was
     * added to it: Weigh & Order, Quotations and New Order all stopped
     * listing that slip until the advance order's time came.
     */
    public function scopeWithoutFutureReservations(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereDoesntHave('sourceQuotation', fn (Builder $quotation) => $quotation->where('scheduled_for', '>', now()))
            ->orWhereHas('items', fn (Builder $item) => $item->where(fn (Builder $line) => $line
                ->whereNull('scheduled_for')
                ->orWhere('scheduled_for', '<=', now()))));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Staff-taken (New Order, the weigh station, a converted quotation) vs
     * a customer's own table/lobby QR self-order — the Kitchen Order Slip
     * uses this to decide between "Waiter" (the staffer who took it) and
     * "Ordered By" (the customer). `order_source` is authoritative once
     * set; older orders predating that column never got a value for it, so
     * they fall back to the one signal that reliably differed between the
     * two paths even before then — a QR order always carries a
     * `guest_session_id`, a staff-taken one never does.
     */
    public function isStaffCreated(): bool
    {
        if ($this->order_source !== null) {
            return $this->order_source === OrderSource::Staff;
        }

        return $this->guest_session_id === null;
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
     * The advance order this slip was OPENED for — a standalone advance
     * order — or null. An advance order added to a slip that was already in
     * use doesn't count: that slip belongs to the party at the table, and the
     * advance-order lines say so on their own ("Advance order QT-00001").
     *
     * sourceQuotation alone can't tell the two apart, because a quotation
     * that joins an existing slip is linked to it the same way. The first
     * round's lines can: they carry the quotation that opened the slip.
     */
    public function openingQuotation(): ?Quotation
    {
        $this->loadMissing(['items.quotation', 'sourceQuotation']);

        // Converted before lines carried their own quotation_id — the
        // order-level link is all there is, so keep reading it as before.
        if ($this->items->every(fn (OrderItem $item) => $item->quotation_id === null)) {
            return $this->sourceQuotation;
        }

        return $this->items
            ->sortBy(fn (OrderItem $item) => [$item->batch_number ?? 0, $item->id])
            ->first()
            ?->quotation;
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
