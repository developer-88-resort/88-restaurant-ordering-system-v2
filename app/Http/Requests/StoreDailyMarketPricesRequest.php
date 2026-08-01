<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyMarketPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('weigh.set_daily_price') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.menu_item_id' => ['required', Rule::exists('menu_items', 'id')->whereNull('deleted_at')],
            // Same 10–10,000 typo guard the menu item form uses — this page
            // exists to catch bad rates, so it cannot be the looser of the
            // two. Blank is allowed and simply means "leave this item on
            // whatever it already resolves to".
            'prices.*.price_per_kilo' => ['nullable', 'numeric', 'min:10', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prices.*.price_per_kilo.min' => __('A market rate below ₱10/kg is almost always a per-100g price typed by mistake.'),
            'prices.*.price_per_kilo.max' => __('A market rate above ₱10,000/kg is almost always a misplaced decimal.'),
        ];
    }
}
