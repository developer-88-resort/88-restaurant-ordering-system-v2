<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The full discount set for a bill that is already paid — see
 * LateDiscountApplier. Same discount rows as checkout; no payments, those
 * are carried over from the bill being corrected.
 */
class ApplyLateDiscountRequest extends FormRequest
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
            'discounts' => ['required', 'array', 'min:1'],
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
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'discounts.required' => __('Pick the discount that was missed.'),
        ];
    }
}
