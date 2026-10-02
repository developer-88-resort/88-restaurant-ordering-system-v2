<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The checkout form's discounts and buyer details, sent to start a Maya
 * Checkout instead of finalizing at the counter. No payments[] — Maya takes
 * the whole bill as one payment, for the amount the server prices.
 */
class StartMayaCheckoutRequest extends FormRequest
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
            // Same shape as FinalizeOrderPaymentRequest's discounts[].
            'discounts' => ['nullable', 'array'],
            'discounts.*.rule_id' => ['required', 'integer', Rule::exists('discount_rules', 'id')],
            'discounts.*.entered_value' => ['nullable', 'numeric', 'min:0'],
            'discounts.*.qualified_name' => ['nullable', 'string', 'max:255'],
            'discounts.*.id_number' => ['nullable', 'string', 'max:100'],
            'discounts.*.reason' => ['nullable', 'string', 'max:500'],
            'discounts.*.item_ids' => ['nullable', 'array'],
            'discounts.*.item_ids.*' => ['integer'],
            'discounts.*.eligible_amount' => ['nullable', 'numeric', 'min:0'],
            // 'per_person' rules (Diplomat): persons in the group, and how many qualify.
            'discounts.*.total_persons' => ['nullable', 'integer', 'min:1', 'max:500'],
            'discounts.*.qualified_persons' => ['nullable', 'integer', 'min:1', 'max:500'],

            'manager_email' => ['nullable', 'email'],
            'manager_password' => ['nullable', 'string'],

            'buyer_name' => ['nullable', 'string', 'max:255'],
            'buyer_tin' => ['nullable', 'string', 'max:50'],
            'buyer_address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
