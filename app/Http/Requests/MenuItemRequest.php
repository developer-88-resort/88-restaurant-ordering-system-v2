<?php

namespace App\Http\Requests;

use App\Enums\MenuItemAvailability;
use App\Enums\PricingType;
use App\Models\MenuItem;
use App\Support\WeighedOrderSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * The ONE request class behind both creating and updating a menu item.
 *
 * Store and update used to carry near-identical copies of these rules, and
 * they had already drifted apart once. A single class means a rule change —
 * especially the per-kilo rules, which decide whether the weigh station can
 * use an item at all — cannot be applied to one route and forgotten on the
 * other.
 */
class MenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** The item being edited, or null when creating. */
    protected function menuItem(): ?MenuItem
    {
        $item = $this->route('menu_item');

        return $item instanceof MenuItem ? $item : null;
    }

    protected function isPerKilo(): bool
    {
        return $this->input('pricing_type') === PricingType::PerKilo->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $menuItem = $this->menuItem();
        $perKilo = $this->isPerKilo();
        $settings = WeighedOrderSettings::current();

        return [
            'menu_category_id' => [
                'required',
                Rule::exists('menu_categories', 'id')
                    ->whereNull('deleted_at')
                    ->where(fn ($query) => $query
                        ->where('is_active', true)
                        ->orWhere('id', $menuItem?->menu_category_id)),
            ],
            'name' => ['required', 'string', 'max:255'],
            // The rich-text editor's HTML markup (headings, lists, color
            // spans, links) eats into this budget faster than the plain
            // text it wraps — sized generously above what a menu blurb
            // realistically needs even heavily formatted.
            'description' => ['nullable', 'string', 'max:20000'],

            // Only required for a fixed item with no variants — see
            // withValidator(). A per-kilo item is priced from the scale.
            'price' => ['nullable', 'numeric', 'min:0'],

            'pricing_type' => ['nullable', Rule::in([PricingType::Fixed->value, PricingType::PerKilo->value])],

            // The reference rate: what the customer is quoted, what prints
            // on the receipt, and what the weigh station compares the keyed
            // amount against. The range is an admin setting, never a
            // constant in here.
            'price_per_kilo' => [
                Rule::requiredIf($perKilo),
                'nullable',
                'numeric',
                'min:'.$settings->pricePerKiloMin(),
                'max:'.$settings->pricePerKiloMax(),
            ],
            // Varies per item — fish, shrimp and crab are not the same — so
            // it is always a field. 250 is only the column/form default.
            'min_weight_grams' => [Rule::requiredIf($perKilo), 'nullable', 'integer', 'min:50', 'max:100000'],

            'sku' => ['nullable', 'string', 'max:100', Rule::unique('menu_items', 'sku')->ignore($menuItem)],
            'prep_time_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'is_featured' => ['nullable', 'boolean'],
            'is_best_seller' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'availability_status' => ['nullable', new Enum(MenuItemAvailability::class)],

            'images' => ['nullable', 'array', 'max:6'],
            'images.*' => ['image', 'max:5120'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => [Rule::exists('menu_item_images', 'id')->where('menu_item_id', $menuItem?->id)],
            'primary_image_id' => ['nullable', Rule::exists('menu_item_images', 'id')->where('menu_item_id', $menuItem?->id)],

            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['nullable', 'string', 'max:255'],
            'variants.*.description' => ['nullable', 'string', 'max:2000'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.price' => ['nullable', 'required_with:variants.*.name', 'numeric', 'min:0'],
            'variants.*.image' => ['nullable', 'image', 'max:5120'],
            'variants.*.remove_image' => ['nullable', 'boolean'],
            'default_variant_index' => ['nullable', 'integer', 'min:0'],

            // Optional extras a customer can add on top of this item (e.g.
            // "Crispy Pata — ₱900") — orthogonal to pricing type and
            // variants, so no cross-field rule needed here the way per-kilo
            // and variants have to exclude each other above.
            'add_ons' => ['nullable', 'array'],
            'add_ons.*.id' => ['nullable', 'integer'],
            'add_ons.*.name' => ['nullable', 'string', 'max:255'],
            'add_ons.*.description' => ['nullable', 'string', 'max:255'],
            // Deliberately NOT required_with:name — some add-ons genuinely
            // have no fixed price (e.g. a "Seafood — customer's choice"
            // shabu-shabu add-on priced per selection at the counter, not
            // per the standard menu rate). A blank price here just means
            // "priced at the counter," not "free" — see syncAddOns() below
            // for how a blank value is stored.
            'add_ons.*.price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $settings = WeighedOrderSettings::current();

        return [
            'price_per_kilo.min' => __('The price per kilo must be between ₱:min and ₱:max.', [
                'min' => number_format((float) $settings->pricePerKiloMin(), 2),
                'max' => number_format((float) $settings->pricePerKiloMax(), 2),
            ]),
            'price_per_kilo.max' => __('The price per kilo must be between ₱:min and ₱:max.', [
                'min' => number_format((float) $settings->pricePerKiloMin(), 2),
                'max' => number_format((float) $settings->pricePerKiloMax(), 2),
            ]),
            'price_per_kilo.required' => __('A reference price per kilo is required for a weighed item.'),
            'min_weight_grams.min' => __('The minimum weight must be at least 50 g.'),
            'min_weight_grams.required' => __('A minimum weight is required for a weighed item.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasVariants = collect($this->input('variants', []))
                ->contains(fn ($row) => trim((string) ($row['name'] ?? '')) !== '');

            if (! $this->isPerKilo() && ! $hasVariants && ! $this->filled('price')) {
                $validator->errors()->add('price', __('Price is required unless you add at least one variant below.'));
            }

            // Variants size a fixed dish; a weighed item's size is whatever
            // the scale says. The two cannot coexist on one item.
            if ($this->isPerKilo() && $hasVariants) {
                $validator->errors()->add('pricing_type', __('A per-kilo item cannot also have variants.'));
            }
        });
    }
}
