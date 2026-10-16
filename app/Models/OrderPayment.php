<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Concerns\RecordsRoomCharge;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One payment entry on one order — split payments are several of these.
 * Card entries record only what the external terminal's printed slip
 * shows (masked): brand, last four digits, reference/approval numbers.
 * The full card number or CVV is never accepted anywhere.
 */
class OrderPayment extends Model
{
    use LogsAuditActivity;
    use RecordsRoomCharge;

    protected $fillable = [
        'order_id',
        'order_invoice_snapshot_id',
        'payment_method',
        'settled_via',
        'status',
        'amount',
        'tendered_amount',
        'change_amount',
        'card_brand',
        // Foreign card: the surcharge on top of what it covers (amount).
        'foreign_card',
        'surcharge_percent',
        'surcharge_amount',
        'card_last_four',
        'terminal_reference',
        'approval_code',
        'terminal_id',
        'reference',
        'charged_to',
        // Room Charge: the room picked from `rooms`, with its number and type
        // code snapshotted, plus who signed for it. See roomLabel().
        'room_id',
        'room_no',
        'room_type_code',
        // Locker / Kubo Charge: which locker or kubo. See spotLabel().
        'spot_no',
        'guest_name',
        'guest_ref',
        'notes',
        'received_by',
        'received_at',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'settled_via' => PaymentMethod::class,
            'status' => OrderPaymentStatus::class,
            'amount' => 'decimal:2',
            'tendered_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'foreign_card' => 'boolean',
            'surcharge_percent' => 'decimal:2',
            'surcharge_amount' => 'decimal:2',
            'received_at' => 'datetime',
            'voided_at' => 'datetime',
            'spot_no' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function invoiceSnapshot(): BelongsTo
    {
        return $this->belongsTo(OrderInvoiceSnapshot::class, 'order_invoice_snapshot_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function evidence(): MorphMany
    {
        return $this->morphMany(MediaEvidence::class, 'evidenceable');
    }

    /**
     * Receipt label, e.g. "Card (Visa)" / "Cash" / "GCash". Older card
     * payments recorded the last 4 digits instead of a card type.
     */
    public function displayLabel(): string
    {
        if ($this->payment_method === PaymentMethod::Card && $this->card_brand) {
            return __('Card (:brand)', ['brand' => \App\Enums\CardBrand::tryFrom($this->card_brand)?->label() ?? $this->card_brand]);
        }

        if ($this->payment_method === PaymentMethod::RoomCharge && $this->roomLabel() !== null) {
            return __('Room Charge — Room :room', ['room' => $this->roomLabel()]);
        }

        if ($this->spotLabel() !== null) {
            return $this->payment_method->label().' — '.$this->spotLabel();
        }

        if ($this->payment_method === PaymentMethod::RoomCharge && $this->settled_via) {
            return __('Room Charge (via :method)', ['method' => $this->settled_via->label()]);
        }

        if ($this->payment_method === PaymentMethod::Card && $this->card_last_four) {
            return __('Card ending in :digits', ['digits' => $this->card_last_four]);
        }

        // Others came back only as a breakfast order's complimentary
        // (App\Support\Breakfast); older "Other" payments keep the plain name.
        if ($this->payment_method === PaymentMethod::Other && $this->received_at?->gte(\App\Support\Breakfast::FROM)) {
            return __('Others — Complimentary');
        }

        return $this->payment_method->label();
    }

    protected function auditLabel(): string
    {
        return 'Payment Entry';
    }

    protected function auditIdentifier(): string
    {
        return $this->payment_method->label().' ₱'.$this->amount;
    }
}
