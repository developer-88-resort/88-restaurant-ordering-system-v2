<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\TaxRegistrationType;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use LogsAuditActivity;

    protected $fillable = [
        'resort_name',
        'address',
        'contact_number',
        'email',
        'opening_time',
        'closing_time',
        'bir_registered_name',
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
        'invoice_number_prefix',
        'invoice_footer_message',
        'service_charge_enabled',
        'service_charge_percent',
        'service_charge_taxable',
        'weigh_customer_confirmation_enabled',
        'weighed_variance_tolerance_amount',
        'weighed_variance_tolerance_percent',
        'weighed_variance_hard_ceiling_percent',
        'weighed_below_minimum_behavior',
        'weighed_price_per_kilo_min',
        'weighed_price_per_kilo_max',
        'weighed_print_slip',
        'reveal_full_discount_id_on_pdf',
    ];

    protected function casts(): array
    {
        return [
            'opening_time' => 'datetime:H:i',
            'closing_time' => 'datetime:H:i',
            'tax_registration_type' => TaxRegistrationType::class,
            'tax_rate' => 'decimal:2',
            'prices_include_vat' => 'boolean',
            'atp_ocn_date_issued' => 'date',
            'service_charge_enabled' => 'boolean',
            'service_charge_percent' => 'decimal:2',
            'service_charge_taxable' => 'boolean',
            'weigh_customer_confirmation_enabled' => 'boolean',
            'weighed_variance_tolerance_amount' => 'decimal:2',
            'weighed_variance_tolerance_percent' => 'decimal:2',
            'weighed_variance_hard_ceiling_percent' => 'decimal:2',
            'weighed_price_per_kilo_min' => 'decimal:2',
            'weighed_price_per_kilo_max' => 'decimal:2',
            'weighed_print_slip' => 'boolean',
            'reveal_full_discount_id_on_pdf' => 'boolean',
        ];
    }

    /**
     * These values mirror the column defaults in the settings migrations.
     * They must be spelled out here too — Eloquent's create() doesn't
     * re-fetch the row after insert, so on the very first call (when the
     * singleton row doesn't exist yet) the in-memory model would otherwise
     * be left with these attributes missing/null even though the DB
     * itself applied its defaults, breaking any code that reads them in
     * that same request.
     */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'resort_name' => '88 Hot Spring Resort',
            'invoice_number_prefix' => '88HSR',
            'tax_registration_type' => TaxRegistrationType::NonVat,
            'tax_rate' => 12.00,
            'prices_include_vat' => true,
            'service_charge_enabled' => false,
            'service_charge_taxable' => false,
            'weigh_customer_confirmation_enabled' => false,
            'weighed_variance_tolerance_amount' => 1.00,
            'weighed_variance_tolerance_percent' => 1.00,
            'weighed_variance_hard_ceiling_percent' => 10.00,
            'weighed_below_minimum_behavior' => 'block',
            'weighed_price_per_kilo_min' => 10.00,
            'weighed_price_per_kilo_max' => 10000.00,
            'weighed_print_slip' => false,
            'reveal_full_discount_id_on_pdf' => true,
        ]);
    }

    /**
     * The BIR-registered legal name, falling back to the trade name
     * (`resort_name`) when it hasn't been filled in yet — so receipts
     * always have a business name even before official BIR paperwork is
     * on file.
     */
    public function invoiceBusinessName(): string
    {
        return $this->bir_registered_name ?: $this->resort_name;
    }

    public function resolvedInvoiceTitle(): string
    {
        return $this->invoice_title ?: $this->tax_registration_type->defaultInvoiceTitle();
    }

    public function resolvedFooterMessage(): string
    {
        return $this->invoice_footer_message ?: __('Thank you for visiting :resort!', ['resort' => $this->resort_name]);
    }

    protected function auditLabel(): string
    {
        return 'Settings';
    }
}
