<?php

namespace App\Http\Requests;

use App\Enums\LineType;
use App\Enums\OrderItemConfirmationStatus;
use App\Models\MenuItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One line appended to an existing order. Deliberately has no price/total
 * field of any kind — the server re-derives the charge from the live menu
 * item (fixed) or from WeighedLinePricer (weighed), so there is nothing a
 * client could submit that would change what is billed.
 */
class AppendOrderItemRequest extends FormRequest
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
        $isWeighed = $this->input('line_type') === LineType::Weighed->value;

        return [
            'menu_item_id' => ['required', Rule::exists('menu_items', 'id')->whereNull('deleted_at')],
            'variant_id' => ['nullable', 'integer', Rule::exists('menu_item_variants', 'id')],
            'quantity' => [Rule::requiredIf(! $isWeighed), 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:255'],

            'line_type' => ['nullable', Rule::in([LineType::Fixed->value, LineType::Weighed->value])],

            // Weighed-line fields. Grams are integers everywhere; the gross
            // reading must be required whenever the line is weighed, since
            // there is no other basis for its price.
            'weight_grams' => [Rule::requiredIf($isWeighed), 'nullable', 'integer', 'min:1', 'max:200000'],
            'tare_grams' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'pieces' => ['nullable', 'integer', 'min:1', 'max:999'],
            // Departing from the day's market rate. Gated by
            // weigh.override_price in withValidator() below — without that
            // check any client could simply post its own ₱/kg and price the
            // line however it liked, which is exactly what the daily market
            // price page exists to prevent.
            'price_per_kilo_snapshot' => ['nullable', 'numeric', 'min:10', 'max:10000'],
            'price_override_reason' => ['nullable', 'string', 'max:255'],
            'cooking_style_id' => ['nullable', Rule::exists('cooking_styles', 'id')],
            'cooking_note' => ['nullable', 'string', 'max:1000'],
            'confirmation_status' => ['nullable', Rule::enum(OrderItemConfirmationStatus::class)],
            'ordered_by_guest_id' => ['nullable', Rule::exists('guest_sessions', 'id')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('line_type') !== LineType::Weighed->value) {
                return;
            }

            // Tare is what's deducted from the gross reading, so a tare at
            // or above it would bill nothing (or negative) for real food.
            if ($this->filled('weight_grams') && (int) $this->input('tare_grams', 0) >= (int) $this->input('weight_grams')) {
                $validator->errors()->add('tare_grams', __('The tare weight must be less than the weight on the scale.'));
            }

            $this->validateAgainstItemLimits($validator);

            if (! $this->filled('price_per_kilo_snapshot')) {
                return;
            }

            if (! $this->user()?->can('weigh.override_price')) {
                $validator->errors()->add('price_per_kilo_snapshot', __('You do not have permission to change the price for this item.'));

                return;
            }

            if (! $this->filled('price_override_reason')) {
                $validator->errors()->add('price_override_reason', __('A reason is required when you change the price.'));
            }
        });
    }

    /**
     * The item's own weight limits, enforced on the NET weight.
     *
     * The wizard shows these live as you key the scale in, but that display
     * is a courtesy — this is the check that actually refuses the line, so
     * the limits hold for anything posting to the endpoint, not just the
     * tablet screen.
     */
    protected function validateAgainstItemLimits($validator): void
    {
        if (! $this->filled('weight_grams') || ! $this->filled('menu_item_id')) {
            return;
        }

        $item = MenuItem::find($this->input('menu_item_id'));

        if (! $item || ! $item->isPerKilo()) {
            return;
        }

        $net = max(0, (int) $this->input('weight_grams') - (int) $this->input('tare_grams', 0));
        $minimum = (int) $item->min_weight_grams;
        $step = max(1, (int) $item->weight_step_grams);

        if ($net < $minimum) {
            $validator->errors()->add('weight_grams', __(
                'The minimum for :item is :min g, but this reads :net g.',
                ['item' => $item->name, 'min' => $minimum, 'net' => $net],
            ));

            return;
        }

        if ($net % $step !== 0) {
            $validator->errors()->add('weight_grams', __(
                'Weight must be in steps of :step g — the nearest accepted weights are :down g and :up g.',
                [
                    'step' => $step,
                    'down' => intdiv($net, $step) * $step,
                    'up' => (intdiv($net, $step) + 1) * $step,
                ],
            ));
        }
    }

    /**
     * A weighed line is always one weighed piece of food, never a countable
     * quantity — the "how much" lives in the grams.
     *
     * @return array<string, mixed>
     */
    public function lineData(): array
    {
        $data = $this->validated();

        $data['line_type'] ??= LineType::Fixed->value;
        $data['menu_item_variant_id'] = $data['variant_id'] ?? null;

        if ($data['line_type'] === LineType::Weighed->value) {
            $data['quantity'] = 1;
            $data['menu_item_variant_id'] = null;
        }

        return $data;
    }
}
