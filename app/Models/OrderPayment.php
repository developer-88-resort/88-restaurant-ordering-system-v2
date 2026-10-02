<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
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
            'received_at' => 'datetime',
            'voided_at' => 'datetime',
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

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * How a room charge gets paid: nothing is collected at the outlet — the
     * guest settles the whole folio at front desk checkout. Older charges
     * kept the "Paid through" mode staff had to pick (almost always Other).
     */
    public function settlementLabel(): string
    {
        return $this->settled_via?->label() ?? __('To be settled at front desk');
    }

    public function isRoomCharge(): bool
    {
        return $this->payment_method === PaymentMethod::RoomCharge;
    }

    /**
     * A room charge from before rooms were picked from a list: only the
     * free text typed into `charged_to` says where it went.
     */
    public function isLegacyRoomCharge(): bool
    {
        return $this->isRoomCharge() && $this->room_no === null;
    }

    /** "511 PH", from the snapshot taken at checkout; null for a legacy charge. */
    public function roomLabel(): ?string
    {
        return $this->room_no !== null ? Room::formatLabel($this->room_no, $this->room_type_code) : null;
    }

    /** The type's full name ("Pension House"), while the room still exists. */
    public function roomTypeName(): ?string
    {
        return $this->room?->roomType?->name;
    }

    /**
     * What `charged_to` says for a picked room — kept written so anything
     * that reads only that column still shows the room: "RM 511 PH — Juan".
     */
    public static function chargedToFor(string $roomLabel, ?string $guestName): string
    {
        $guestName = trim((string) $guestName);

        return 'RM '.$roomLabel.($guestName !== '' ? ' — '.$guestName : '');
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

        if ($this->payment_method === PaymentMethod::RoomCharge && $this->settled_via) {
            return __('Room Charge (via :method)', ['method' => $this->settled_via->label()]);
        }

        if ($this->payment_method === PaymentMethod::Card && $this->card_last_four) {
            return __('Card ending in :digits', ['digits' => $this->card_last_four]);
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
