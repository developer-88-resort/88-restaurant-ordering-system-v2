<?php

namespace App\Http\Requests;

use App\Enums\SpaceStatus;
use App\Http\Requests\Concerns\ValidatesMenuItemAddOnSelections;
use App\Http\Requests\Concerns\ValidatesMenuItemVariantSelections;
use App\Models\Order;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Services\OrderAppender;
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
            'space_id' => ['nullable', Rule::exists('spaces', 'id')->whereNull('deleted_at')],
            // Empty = start a new slip (the default). Set = add this cart to
            // one of the table's open slips instead.
            'target_order_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:255'],
            // Who the order page was drawn for (orders/create.blade.php).
            // A cart started under someone who has since signed out of a
            // shared tablet is refused rather than placed under the next
            // person — see withValidator().
            'cart_owner_id' => ['sometimes', 'integer'],
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
        $validator->after(function (Validator $validator) {
            if ($this->has('cart_owner_id') && $this->integer('cart_owner_id') !== $this->user()->id) {
                $validator->errors()->add('cart_owner_id', __('This order was started under a different sign-in, so it was not placed. Please build it again.'));
            }
        });

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

            // An occupied table takes more orders — each one is a new slip
            // on its tab. Reserved, under maintenance or disabled does not.
            if (! in_array($space->status, [SpaceStatus::Available, SpaceStatus::Occupied], true)) {
                $validator->errors()->add('space_id', __('":name" is no longer available. Please pick another table.', ['name' => $space->name]));

                return;
            }

            if ($this->filled('target_order_id') && ! $this->joinableSlip($space)) {
                $validator->errors()->add('target_order_id', __('That slip can no longer take items. Start a new slip instead.'));
            }
        });
    }

    /**
     * The open slip staff chose to add this cart to, or null for "new slip".
     * Only meaningful after validation has passed.
     */
    public function targetOrder(): ?Order
    {
        if (! $this->filled('target_order_id') || $this->input('order_type') !== 'dine_in') {
            return null;
        }

        $space = Space::find($this->input('space_id'));

        return $space ? $this->joinableSlip($space) : null;
    }

    /**
     * The chosen slip, if it is still one of this table's open slips and
     * can still take lines (not paid, invoiced, cancelled, or on a closed
     * tab).
     */
    protected function joinableSlip(Space $space): ?Order
    {
        $order = OrderAppender::findOpenOrdersForTable($space)->firstWhere('id', $this->integer('target_order_id'));

        return $order && OrderAppender::canAppend($order) ? $order : null;
    }
}
