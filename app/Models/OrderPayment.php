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

    public function evidence(): MorphMany
    {
        return $this->morphMany(MediaEvidence::class, 'evidenceable');
    }

    /**
     * Receipt label, e.g. "Card ending in 1234" / "Cash" / "GCash".
     */
    public function displayLabel(): string
    {
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
