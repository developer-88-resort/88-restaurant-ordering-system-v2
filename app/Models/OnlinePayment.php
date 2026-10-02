<?php

namespace App\Models;

use App\Enums\OnlinePaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlinePayment extends Model
{
    protected $fillable = [
        'order_id',
        'provider',
        'request_reference_number',
        'checkout_id',
        'redirect_url',
        'amount',
        'status',
        'provider_status',
        'checkout_data',
        'approved_by',
        'initiated_by',
        'error_message',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => OnlinePaymentStatus::class,
            'checkout_data' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
