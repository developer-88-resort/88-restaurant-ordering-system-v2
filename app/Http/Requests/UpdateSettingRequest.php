<?php

namespace App\Http\Requests;

use App\Enums\TaxRegistrationType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'resort_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'opening_time' => ['nullable', 'date_format:H:i'],
            'closing_time' => ['nullable', 'date_format:H:i'],

            'bir_registered_name' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:50'],
            'branch_code' => ['nullable', 'string', 'max:50'],
            'tax_registration_type' => ['required', Rule::enum(TaxRegistrationType::class)],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'prices_include_vat' => ['nullable', 'boolean'],
            'invoice_title' => ['nullable', 'string', 'max:255', 'regex:/invoice/i'],
            'bir_permit_number' => ['nullable', 'string', 'max:100'],
            'atp_ocn_number' => ['nullable', 'string', 'max:100'],
            'atp_ocn_date_issued' => ['nullable', 'date'],
            'invoice_serial_from' => ['nullable', 'string', 'max:50'],
            'invoice_serial_to' => ['nullable', 'string', 'max:50'],
            'invoice_number_prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'invoice_footer_message' => ['nullable', 'string', 'max:1000'],
            'service_charge_enabled' => ['nullable', 'boolean'],
            'service_charge_percent' => ['nullable', 'required_if:service_charge_enabled,1', 'numeric', 'min:0', 'max:100'],
            'service_charge_taxable' => ['nullable', 'boolean'],
            'weigh_customer_confirmation_enabled' => ['nullable', 'boolean'],
            'reveal_full_discount_id_on_pdf' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice_title.regex' => __('The invoice title must contain the word "Invoice".'),
            'invoice_number_prefix.regex' => __('The invoice number prefix may only contain letters and numbers.'),
        ];
    }
}
