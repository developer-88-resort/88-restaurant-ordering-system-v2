<?php

namespace App\Models;

use App\Enums\DiscountCalculationMode;
use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One applied-discount line frozen onto one invoice snapshot. Immutable
 * after creation, like its parent snapshot — corrections happen by voiding
 * the invoice and re-finalizing, never by editing these rows.
 */
class OrderInvoiceDiscount extends Model
{
    protected $fillable = [
        'order_invoice_snapshot_id',
        'discount_rule_id',
        'rule_name',
        'rule_code',
        'calculation_mode',
        'entered_value',
        'statutory_type',
        'eligible_amount',
        'calculated_amount',
        'vat_exemption_amount',
        'qualified_name',
        'id_number',
        'reason',
        'applied_by',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'calculation_mode' => DiscountCalculationMode::class,
            'entered_value' => 'decimal:2',
            'statutory_type' => DiscountType::class,
            'eligible_amount' => 'decimal:2',
            'calculated_amount' => 'decimal:2',
            'vat_exemption_amount' => 'decimal:2',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(OrderInvoiceSnapshot::class, 'order_invoice_snapshot_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(DiscountRule::class, 'discount_rule_id');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
