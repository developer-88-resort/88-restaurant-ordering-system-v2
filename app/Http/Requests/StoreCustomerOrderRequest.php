<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesMenuItemVariantSelections;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Deliberately narrower than StoreOrderRequest (the staff version): a
 * customer request never carries order_type/area_id/space_category_id/
 * space_id — those are 100% derived server-side from the {space:qr_token}
 * the customer scanned, so an anonymous POST can never redirect its own
 * order to a different table.
 */
class StoreCustomerOrderRequest extends FormRequest
{
    use ValidatesMenuItemVariantSelections;

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
            'notes' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            // Client-generated per-submission key; the unique index on
            // orders.idempotency_key turns accidental double-taps into a
            // redirect to the already-created order.
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => [
                'required',
                Rule::exists('menu_items', 'id')->whereIn('availability_status', ['available', 'seasonal'])->whereNull('deleted_at'),
            ],
            // Uniqueness/required-if-has-variants is enforced on the
            // (menu_item_id, menu_item_variant_id) pair in withValidator()
            // below, not here — a customer can legitimately order the same
            // item twice with two different variants.
            'items.*.menu_item_variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateVariantSelections($validator));
    }
}
