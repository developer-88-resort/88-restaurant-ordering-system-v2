<?php

namespace App\Http\Requests;

use App\Enums\OrderItemAdjustmentReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelOrderItemRequest extends FormRequest
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
            'quantity' => ['required', 'integer', 'min:1'],
            'reason_code' => ['required', Rule::enum(OrderItemAdjustmentReason::class)],
            // A preset reason speaks for itself; "Other" is the one that
            // needs the words written down.
            'notes' => ['nullable', 'required_if:reason_code,'.OrderItemAdjustmentReason::Other->value, 'string', 'max:1000'],
            'inventory_restored' => ['nullable', 'boolean'],
            // A manager proves it with their PIN (picked from a list) or with
            // email + password — see App\Support\ManagerApproval.
            'manager_id' => ['nullable', 'integer'],
            'manager_pin' => ['nullable', 'string', 'max:12'],
            'manager_email' => ['nullable', 'email'],
            'manager_password' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'notes.required_if' => __('Please describe the reason when choosing "Other".'),
        ];
    }
}
