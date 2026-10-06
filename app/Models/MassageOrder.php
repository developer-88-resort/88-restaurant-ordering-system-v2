<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\MassageOrderStatus;
use App\Enums\OrderPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class MassageOrder extends Model
{
    use LogsAuditActivity;

    protected $fillable = [
        'order_number',
        'room_number',
        'guest_name',
        'notes',
        'status',
        'total_amount',
        'created_by',
        'paid_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MassageOrderStatus::class,
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(MassageOrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(MassagePayment::class);
    }

    public function recordedPayments(): HasMany
    {
        return $this->payments()->where('status', OrderPaymentStatus::Recorded);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->status === MassageOrderStatus::Pending;
    }

    /** Reports print an order the same way for restaurant and massage. */
    public function orderNumber(): string
    {
        return (string) $this->order_number;
    }

    public function slipLocationLabel(): string
    {
        return $this->whoLabel();
    }

    /** "Room 204 · Ana", "Room 204", "Ana", or "Walk-in". */
    public function whoLabel(): string
    {
        $parts = array_filter([
            $this->room_number ? __('Room :number', ['number' => $this->room_number]) : null,
            $this->guest_name,
        ]);

        return $parts ? implode(' · ', $parts) : __('Walk-in');
    }

    /**
     * MS-MMDD-001: a day's massage orders count up from 001, like the
     * restaurant's slip numbers but with their own prefix and sequence.
     * Called inside the creating transaction; the row lock keeps two
     * counters from taking the same number.
     */
    public static function nextOrderNumber(): string
    {
        $prefix = 'MS-'.now()->format('md').'-';

        $last = DB::table('massage_orders')
            ->where('order_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('order_number')
            ->value('order_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    protected function auditLabel(): string
    {
        return 'Massage Order';
    }

    protected function auditIdentifier(): string
    {
        return (string) $this->order_number;
    }
}
