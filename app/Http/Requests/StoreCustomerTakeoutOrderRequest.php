<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesMenuItemAddOnSelections;
use App\Http\Requests\Concerns\ValidatesMenuItemVariantSelections;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The Welcome (lobby QR) flow's "Order Food" tile — a takeout order with
 * no table/space at all, unlike StoreCustomerOrderRequest which always
 * derives its space from a scanned {space:qr_token}.
 */
class StoreCustomerTakeoutOrderRequest extends FormRequest
{
    use ValidatesMenuItemAddOnSelections;
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => [
                'required',
                Rule::exists('menu_items', 'id')->whereIn('availability_status', ['available', 'seasonal'])->whereNull('deleted_at'),
            ],
            'items.*.menu_item_variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'items.*.add_ons' => ['nullable', 'array', 'max:50'],
            'items.*.add_ons.*.id' => ['required_with:items.*.add_ons', 'integer'],
            'items.*.add_ons.*.quantity' => ['required_with:items.*.add_ons', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateVariantSelections($validator));
        $validator->after(fn (Validator $validator) => $this->validateAddOnSelections($validator));
    }
}
