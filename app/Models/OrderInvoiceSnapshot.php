<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\DiscountEligibilityMethod;
use App\Enums\DiscountType;
use App\Enums\InvoiceSnapshotStatus;
use App\Enums\PaymentMethod;
use App\Enums\TaxRegistrationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;

/**
 * A frozen, immutable copy of everything needed to render one invoice,
 * captured once at payment-finalization time. Extends this codebase's
 * existing snapshot-at-creation philosophy (order_items already freeze
 * item_name/unit_price from the live MenuItem) one level up to the whole
 * invoice, so a later Settings/tax-rate change never rewrites an
 * already-issued invoice.
 *
 * Deliberately 1:many with Order — once an invoice number is issued it
 * stays on permanent record even if voided; a repay after void creates a
 * brand new row with the next sequential number rather than mutating or
 * reusing this one. `status` tracks that independently of Order's own
 * mutable payment_status.
 */
class OrderInvoiceSnapshot extends Model
{
    use LogsAuditActivity {
        getActivitylogOptions as baseActivitylogOptions;
    }

    protected $fillable = [
        'order_id',
        'invoice_number',
        'status',
        'voided_at',
        'voided_by',
        'business_name',
        'trade_name',
        'business_address',
        'contact_number',
        'email',
        'website',
        'tin',
        'branch_code',
        'tax_registration_type',
        'tax_rate',
        'prices_include_vat',
        'invoice_title',
        'bir_permit_number',
        'atp_ocn_number',
        'atp_ocn_date_issued',
        'invoice_serial_from',
        'invoice_serial_to',
        'footer_message',
        'gross_sales',
        'vatable_sales',
        'vat_exempt_sales',
        'zero_rated_sales',
        'vat_amount',
        'vat_exemption_amount',
        'service_charge_enabled',
        'service_charge_percent',
        'service_charge_amount',
        'service_charge_taxable',
        'discount_type',
        'discount_qualified_name',
        'discount_id_number',
        'discount_eligibility_method',
        'discount_eligible_item_names',
        'discount_eligible_amount',
        'discount_amount',
        'discount_promo_percent',
        'discount_qualified_diners',
        'discount_total_diners',
        'discount_notes',
        'buyer_name',
        'buyer_tin',
        'buyer_address',
        'rounding_adjustment',
        'total_amount_due',
        'payment_method',
        'payment_reference',
        'amount_received',
        'change_amount',
        'computed_by',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceSnapshotStatus::class,
            'voided_at' => 'datetime',
            'tax_registration_type' => TaxRegistrationType::class,
            'tax_rate' => 'decimal:2',
            'prices_include_vat' => 'boolean',
            'atp_ocn_date_issued' => 'date',
            'gross_sales' => 'decimal:2',
            'vatable_sales' => 'decimal:2',
            'vat_exempt_sales' => 'decimal:2',
            'zero_rated_sales' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'vat_exemption_amount' => 'decimal:2',
            'service_charge_enabled' => 'boolean',
            'service_charge_percent' => 'decimal:2',
            'service_charge_amount' => 'decimal:2',
            'service_charge_taxable' => 'boolean',
            'discount_type' => DiscountType::class,
            'discount_eligibility_method' => DiscountEligibilityMethod::class,
            'discount_eligible_item_names' => 'array',
            'discount_eligible_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'discount_promo_percent' => 'decimal:2',
            'rounding_adjustment' => 'decimal:2',
            'total_amount_due' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'amount_received' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'computed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The per-line discount records frozen with this invoice (several when
     * stacking was allowed). The legacy single-discount columns above stay
     * populated when exactly one discount applied, for backward compat.
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(OrderInvoiceDiscount::class);
    }

    /**
     * Payment entries settled against this specific invoice (split
     * payments are several rows).
     */
    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class, 'order_invoice_snapshot_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function computedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'computed_by');
    }

    /**
     * The real ID number is required on the official invoice for BIR
     * substantiation, but it never needs to be duplicated into the audit
     * trail's properties JSON.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return $this->baseActivitylogOptions()->logExcept(['discount_id_number']);
    }

    protected function auditLabel(): string
    {
        return 'Invoice';
    }

    protected function auditIdentifier(): string
    {
        return $this->invoice_number;
    }
}
