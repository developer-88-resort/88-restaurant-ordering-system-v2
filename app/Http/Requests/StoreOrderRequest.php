<?php

namespace App\Http\Requests;

use App\Enums\SpaceStatus;
use App\Http\Requests\Concerns\ValidatesMenuItemAddOnSelections;
use App\Http\Requests\Concerns\ValidatesMenuItemVariantSelections;
use App\Models\Space;
use App\Models\SpaceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
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
            'order_type' => ['required', 'in:dine_in,takeout'],
            'pax' => ['nullable', 'integer', 'min:1', 'max:999'],
            'area_id' => ['required_if:order_type,dine_in', 'nullable', 'exists:areas,id'],
            'space_category_id' => ['required_if:order_type,dine_in', 'nullable', 'exists:space_categories,id'],
            'space_id' => ['nullable', 'exists:spaces,id'],
            'notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', Rule::exists('menu_items', 'id')->whereNull('deleted_at')],
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

        $validator->after(function (Validator $validator) {
            if ($this->input('order_type') !== 'dine_in') {
                return;
            }

            $category = SpaceCategory::find($this->input('space_category_id'));

            if (! $category) {
                return;
            }

            if ($category->is_free) {
                if ($category->isFull()) {
                    $validator->errors()->add('space_category_id', __('":name" is full.', ['name' => $category->name]));
                }

                return;
            }

            if (! $this->filled('space_id')) {
                $validator->errors()->add('space_id', __('Please select a space.'));

                return;
            }

            $space = Space::find($this->input('space_id'));

            if (! $space || $space->category_id !== $category->id) {
                $validator->errors()->add('space_id', __('The selected space does not belong to this category.'));

                return;
            }

            if ($space->status !== SpaceStatus::Available) {
                $validator->errors()->add('space_id', __('":name" is no longer available. Please pick another table.', ['name' => $space->name]));
            }
        });
    }
}
