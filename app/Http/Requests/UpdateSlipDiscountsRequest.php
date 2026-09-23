<?php

namespace App\Http\Requests;

use App\Enums\DiscountCalculationMode;
use App\Models\DiscountRule;
use App\Models\Order;
use App\Services\Printing\OrderSlipTotals;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

/**
 * The discounts to show on an order slip, picked on the Kitchen Display.
 *
 * Offers the same discount rules as checkout and follows the same shape
 * (a custom rule needs its value, an item-scope rule needs its items or an
 * amount, an exclusive rule stands alone, a minimum bill is a minimum bill),
 * but it only ever changes the slip: no manager approval, no ID number, and
 * nothing here reaches the receipt. See OrderSlipTotals for the maths.
 */
class UpdateSlipDiscountsRequest extends FormRequest
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
            // An empty list takes every discount off the slip.
            'discounts' => ['present', 'array', 'max:5'],
            'discounts.*.rule_id' => ['required', 'integer', 'distinct'],
            'discounts.*.value' => ['nullable', 'numeric', 'min:0.01', 'max:1000000'],
            'discounts.*.item_ids' => ['nullable', 'array'],
            'discounts.*.item_ids.*' => ['integer'],
            'discounts.*.eligible_amount' => ['nullable', 'numeric', 'min:0.01', 'max:1000000'],
            'discounts.*.qualified_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $rows = collect($this->input('discounts', []));
            $rules = $this->availableRules();
            $order = $this->order();
            $subtotal = OrderSlipTotals::subtotal($order);
            $liveItemIds = $order->items->reject->isFullyCancelled()->pluck('id')->all();

            if ($rows->count() > 1 && $rows->contains(fn ($row) => $rules->has((int) $row['rule_id']) && ! $rules[(int) $row['rule_id']]->is_stackable)) {
                $validator->errors()->add('discounts', __(':name cannot be combined with another discount.', [
                    'name' => $rows->map(fn ($row) => $rules->get((int) $row['rule_id']))->filter()->first(fn ($rule) => ! $rule->is_stackable)->name,
                ]));

                return;
            }

            $statutoryBasis = '0.00';

            foreach ($rows as $index => $row) {
                $rule = $rules->get((int) $row['rule_id']);

                if (! $rule) {
                    $validator->errors()->add("discounts.{$index}.rule_id", __('That discount is no longer available.'));

                    continue;
                }

                if ($rule->min_bill_amount !== null && bccomp($subtotal, (string) $rule->min_bill_amount, 2) < 0) {
                    $validator->errors()->add("discounts.{$index}.rule_id", __(':name needs a bill of at least ₱:amount.', [
                        'name' => $rule->name,
                        'amount' => number_format((float) $rule->min_bill_amount, 2),
                    ]));
                }

                if ($rule->is_custom_value) {
                    $value = $row['value'] ?? null;
                    if ($value === null || $value === '') {
                        $validator->errors()->add("discounts.{$index}.value", __('Enter the value for :name.', ['name' => $rule->name]));
                    } elseif ($rule->calculation_mode === DiscountCalculationMode::Percent && (float) $value > 100) {
                        $validator->errors()->add("discounts.{$index}.value", __('A percentage cannot be more than 100.'));
                    }
                }

                if ($rule->scope !== 'eligible_items') {
                    continue;
                }

                $itemIds = array_map('intval', $row['item_ids'] ?? []);
                $amount = $row['eligible_amount'] ?? null;

                if ($itemIds === [] && ($amount === null || $amount === '')) {
                    $validator->errors()->add("discounts.{$index}.item_ids", __(':name needs eligible items or an eligible amount.', ['name' => $rule->name]));

                    continue;
                }

                if (array_diff($itemIds, $liveItemIds) !== []) {
                    $validator->errors()->add("discounts.{$index}.item_ids", __('One or more selected items do not belong to this order.'));

                    continue;
                }

                if ($itemIds === [] && bccomp((string) $amount, $subtotal, 2) > 0) {
                    $validator->errors()->add("discounts.{$index}.eligible_amount", __('The eligible amount for :name cannot exceed the order total.', ['name' => $rule->name]));

                    continue;
                }

                if ($rule->isStatutory()) {
                    $statutoryBasis = bcadd($statutoryBasis, OrderSlipTotals::basis($order, $this->entry($rule, $row), $subtotal), 2);
                }
            }

            // Senior and PWD come out of the same meals — together they can't
            // cover more than the slip, same as at checkout.
            if (bccomp($statutoryBasis, $subtotal, 2) > 0) {
                $validator->errors()->add('discounts', __('Combined Senior/PWD eligible amounts cannot exceed the order total.'));
            }
        });
    }

    /**
     * The validated discounts as stored on the order: each rule's name, mode
     * and value frozen as they are now, so a later edit to the rule in
     * Settings doesn't quietly change a slip that was already printed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function entries(): array
    {
        $rules = $this->availableRules();

        return collect($this->validated('discounts'))
            ->map(fn ($row) => ['rule' => $rules[(int) $row['rule_id']], 'row' => $row])
            ->sortBy(fn ($pair) => $pair['rule']->priority)
            ->map(fn ($pair) => $this->entry($pair['rule'], $pair['row']))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    protected function entry(DiscountRule $rule, array $row): array
    {
        $itemScoped = $rule->scope === 'eligible_items';
        $itemIds = $itemScoped ? array_values(array_unique(array_map('intval', $row['item_ids'] ?? []))) : [];
        $name = trim((string) ($row['qualified_name'] ?? ''));

        return [
            'rule_id' => $rule->id,
            'name' => $rule->name,
            'mode' => $rule->calculation_mode->value,
            'value' => $rule->is_custom_value ? (string) $row['value'] : (string) $rule->value,
            'scope' => $itemScoped ? 'eligible_items' : 'whole_bill',
            'item_ids' => $itemIds,
            'eligible_amount' => $itemScoped && $itemIds === [] && isset($row['eligible_amount']) ? (string) $row['eligible_amount'] : null,
            'qualified_name' => $name !== '' ? $name : null,
            'max_amount' => $rule->max_discount_amount !== null ? (string) $rule->max_discount_amount : null,
        ];
    }

    protected function order(): Order
    {
        return $this->route('order')->loadMissing('items.adjustments');
    }

    /** @return Collection<int, DiscountRule> The rules a slip may use right now, by id. */
    protected function availableRules(): Collection
    {
        return once(fn () => DiscountRule::currentlyAvailable()->get()->keyBy('id'));
    }
}
