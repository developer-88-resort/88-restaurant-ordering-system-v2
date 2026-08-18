<?php

namespace App\Http\Requests;

use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuotationRequest extends FormRequest
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
            'space_id' => ['required', Rule::exists('spaces', 'id')],
            // 'new' starts a fresh receipt; anything else is the id of the
            // exact open order this batch should join — chosen explicitly by
            // staff, never guessed. Re-validated as still-open at commit
            // time in the controller, since it may have been paid/closed
            // between the panel loading and this submit.
            'target' => ['required', function (string $attribute, mixed $value, \Closure $fail) {
                if ($value === 'new') {
                    return;
                }

                if (! ctype_digit((string) $value) || ! Order::whereKey($value)->exists()) {
                    $fail(__('Pick a receipt to add this to, or start a new one.'));
                }
            }],
            // The create screen generates one UUID per form load and resends
            // it unchanged on retry — a double-click submit is recognised as
            // the same request instead of creating a second quotation.
            'request_id' => ['required', 'string', 'uuid'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_contact' => ['nullable', 'string', 'max:255'],
            'scheduled_for' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', Rule::exists('menu_items', 'id')->whereNull('deleted_at')],
            'items.*.menu_item_variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * A weight-priced item (Bangus and the like) has no fixed price to
     * freeze into a quotation — it can only be priced honestly once it's
     * actually on the scale. Blocking it here is the server half of Section
     * 7's rule; the create screen's job is to never let one get this far.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $menuItemIds = collect($this->input('items', []))->pluck('menu_item_id')->filter()->unique();

            if ($menuItemIds->isEmpty()) {
                return;
            }

            $counterOnlyIds = MenuItem::whereIn('id', $menuItemIds)
                ->where('counter_only', true)
                ->pluck('id');

            foreach ($this->input('items', []) as $index => $line) {
                if ($counterOnlyIds->contains($line['menu_item_id'] ?? null)) {
                    $validator->errors()->add(
                        "items.{$index}.menu_item_id",
                        "it cannot be ordered unless youve call the staff or thru personal ordering"
                    );
                }
            }
        });
    }
}
