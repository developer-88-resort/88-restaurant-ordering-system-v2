<?php

namespace App\Http\Requests;

use App\Enums\DiscountEligibilityMethod;
use App\Enums\DiscountType;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinalizeOrderPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Legacy single-payment shape — still accepted; required only
            // when the new payments[] array isn't supplied.
            'payment_method' => ['required_without:payments', 'nullable', Rule::enum(PaymentMethod::class)],
            'payment_reference' => ['exclude_with:payments', 'required_unless:payment_method,cash', 'nullable', 'string', 'max:100'],
            'amount_received' => ['required_without:payments', 'nullable', 'numeric', 'min:0'],

            // Split-payment entries. Per-method requirements (card terminal
            // reference, cash tendered, duplicate detection) are enforced in
            // PaymentFinalizer where the computed total is known.
            // SECURITY: no field for a full card number or CVV exists at
            // all — only masked last-four/terminal-slip details.
            'payments' => ['nullable', 'array', 'min:1'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            // min:0 (not 0.01) so a fully-discounted zero-balance bill can
            // still be closed with a single zero cash entry.
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
            'payments.*.tendered_amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.card_brand' => ['nullable', 'string', 'max:30'],
            'payments.*.card_last_four' => ['nullable', 'digits:4'],
            'payments.*.terminal_reference' => ['nullable', 'string', 'max:100'],
            'payments.*.approval_code' => ['nullable', 'string', 'max:100'],
            'payments.*.terminal_id' => ['nullable', 'string', 'max:100'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            'payments.*.notes' => ['nullable', 'string', 'max:500'],

            // Configurable multi-discount shape (discount_rules table).
            'discounts' => ['nullable', 'array'],
            'discounts.*.rule_id' => ['required', 'integer', Rule::exists('discount_rules', 'id')],
            'discounts.*.entered_value' => ['nullable', 'numeric', 'min:0'],
            'discounts.*.qualified_name' => ['nullable', 'string', 'max:255'],
            'discounts.*.id_number' => ['nullable', 'string', 'max:100'],
            'discounts.*.reason' => ['nullable', 'string', 'max:500'],
            'discounts.*.item_ids' => ['nullable', 'array'],
            'discounts.*.item_ids.*' => ['integer'],
            'discounts.*.eligible_amount' => ['nullable', 'numeric', 'min:0'],

            // Manager re-authentication for approvals (staff only; admins
            // approve their own actions).
            'manager_email' => ['nullable', 'email'],
            'manager_password' => ['nullable', 'string'],

            'discount_type' => ['nullable', Rule::enum(DiscountType::class)],
            'discount_qualified_name' => ['required_if:discount_type,senior_citizen,pwd', 'nullable', 'string', 'max:255'],
            'discount_id_number' => ['required_if:discount_type,senior_citizen,pwd', 'nullable', 'string', 'max:100'],
            'discount_promo_percent' => ['required_if:discount_type,promo', 'nullable', 'numeric', 'min:0', 'max:100'],
            'discount_eligibility_method' => ['required_with:discount_type', 'nullable', Rule::enum(DiscountEligibilityMethod::class)],
            'discount_item_ids' => ['required_if:discount_eligibility_method,item_based', 'nullable', 'array', 'min:1'],
            'discount_item_ids.*' => ['integer'],
            'discount_eligible_amount' => ['required_if:discount_eligibility_method,amount_based', 'nullable', 'numeric', 'min:0'],
            'discount_qualified_diners' => ['nullable', 'integer', 'min:1'],
            'discount_total_diners' => ['nullable', 'integer', 'min:1', 'gte:discount_qualified_diners'],
            'discount_notes' => ['nullable', 'string', 'max:500'],

            'buyer_name' => ['nullable', 'string', 'max:255'],
            'buyer_tin' => ['nullable', 'string', 'max:50'],
            'buyer_address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
