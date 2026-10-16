<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Concerns\RecordsRoomCharge;
use App\Enums\CardBrand;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment on a massage order — the same fields and rules as the
 * restaurant's order_payments (PaymentFinalizer::validatePayments), so a
 * Room Charge goes on a room picked from `rooms` (see RecordsRoomCharge).
 */
class MassagePayment extends Model
{
    use LogsAuditActivity;
    use RecordsRoomCharge;

    protected $fillable = [
        'massage_order_id',
        'payment_method',
        'settled_via',
        'charged_to',
        'room_id',
        'room_no',
        'room_type_code',
        'spot_no',
        'guest_name',
        'guest_ref',
        'status',
        'amount',
        'tendered_amount',
        'change_amount',
        'card_brand',
        // Foreign card: the surcharge on top of what it covers (amount).
        'foreign_card',
        'surcharge_percent',
        'surcharge_amount',
        'reference',
        'approval_code',
        'notes',
        'received_by',
        'received_at',
        'voided_by',
        'voided_at',
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
        return $this->belongsTo(MassageOrder::class, 'massage_order_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function displayLabel(): string
    {
        if ($this->payment_method === PaymentMethod::RoomCharge && $this->roomLabel() !== null) {
            return __('Room Charge — Room :room', ['room' => $this->roomLabel()]);
        }

        if ($this->spotLabel() !== null) {
            return $this->payment_method->label().' — '.$this->spotLabel();
        }

        if ($this->payment_method === PaymentMethod::RoomCharge && $this->settled_via) {
            return __('Room Charge (via :method)', ['method' => $this->settled_via->label()]);
        }

        if ($this->payment_method === PaymentMethod::Card && $this->card_brand) {
            return __('Card (:brand)', ['brand' => CardBrand::tryFrom($this->card_brand)?->label() ?? $this->card_brand]);
        }

        return $this->payment_method->label();
    }

    protected function auditLabel(): string
    {
        return 'Massage Payment';
    }
}
