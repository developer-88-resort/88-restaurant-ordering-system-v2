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
            'notes' => ['required', 'string', 'max:1000'],
            'inventory_restored' => ['nullable', 'boolean'],
            'manager_email' => ['nullable', 'email'],
            'manager_password' => ['nullable', 'string'],
        ];
    }
}
