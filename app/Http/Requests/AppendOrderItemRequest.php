<?php

namespace App\Http\Requests;

use App\Enums\AmountSource;
use App\Enums\LineType;
use App\Enums\OrderItemConfirmationStatus;
use App\Enums\WeighEntryMode;
use App\Models\MenuItem;
use App\Support\WeighedOrderSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One line appended to an order that already exists.
 *
 * Deliberately has no line_total, computed_amount or reference rate field:
 * a fixed line is re-priced from the live menu item, and a weighed line's
 * reference rate is resolved server-side for the day it is recorded. The
 * ONE money figure a client may send is `amount_charged` — because that is
 * the number a person read off the counter scale, and no server can know
 * it. Everything else it might claim, the server works out for itself.
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
        $isWeighed = $this->isWeighed();

        return [
            'menu_item_id' => ['required', Rule::exists('menu_items', 'id')->whereNull('deleted_at')],
            'variant_id' => ['nullable', 'integer', Rule::exists('menu_item_variants', 'id')],
            'quantity' => [Rule::requiredIf(! $isWeighed), 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:255'],

            'line_type' => ['nullable', Rule::in([LineType::Fixed->value, LineType::Weighed->value])],

            // Both figures come off the scale's display. net_grams is
            // already net — the hardware's TARE button did that.
            'net_grams' => [Rule::requiredIf($isWeighed), 'nullable', 'integer', 'min:1', 'max:200000'],
            'amount_charged' => [Rule::requiredIf($isWeighed), 'nullable', 'numeric', 'gt:0', 'max:1000000'],
            // 'computed' when the "Use expected" shortcut filled the amount
            // instead of a person reading it off the display — kept apart
            // from entry_mode, which is about the WEIGHT source, not the money.
            'amount_source' => ['nullable', Rule::enum(AmountSource::class)],

            'pieces' => ['nullable', 'integer', 'min:1', 'max:999'],
            // A weighed line always says how it is to be cooked: the
            // kitchen cannot start on a fish with no instruction, and the
            // surcharge rides on the style.
            'cooking_style_id' => [Rule::requiredIf($isWeighed), 'nullable', Rule::exists('cooking_styles', 'id')],
            'cooking_note' => ['nullable', 'string', 'max:1000'],

            // Required only when the keyed amount lands outside tolerance;
            // WeighedLineRecorder decides that, because the tolerance is an
            // admin setting and this request must not second-guess it.
            'variance_reason' => ['nullable', 'string', 'max:500'],

            'entry_mode' => ['nullable', Rule::enum(WeighEntryMode::class)],
            'confirmation_status' => ['nullable', Rule::enum(OrderItemConfirmationStatus::class)],
            'ordered_by_guest_id' => ['nullable', Rule::exists('guest_sessions', 'id')],

            // Optional extras on this one line — fixed-price, quantity
            // based, summed on top of whatever amount_charged/unit_price
            // this line already carries. Works for both fixed and weighed
            // lines through this endpoint (see withValidator() below for
            // the per-item "does this item actually offer this add-on"
            // check, which items[]-shaped ValidatesMenuItemAddOnSelections
            // doesn't fit since this request is a single flat line).
            'add_ons' => ['nullable', 'array', 'max:50'],
            'add_ons.*.id' => ['required_with:add_ons', 'integer'],
            'add_ons.*.quantity' => ['required_with:add_ons', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $item = $this->menuItem();

            if (! $item) {
                return;
            }

            // A per-kilo item has no unit price to multiply by a quantity.
            // Letting the fixed path through would bill it at ₱0.00, which
            // is exactly how the legacy #88-0801-002 lines happened.
            if ($item->isPerKilo() && ! $this->isWeighed()) {
                $validator->errors()->add('menu_item_id', __('Weighed at the counter. Ask our staff to weigh this for you.'));

                return;
            }

            if (! $this->isWeighed()) {
                return;
            }

            // The app no longer deducts a tare anywhere. A payload that
            // still sends one is running against an older contract and
            // would bill for more than the customer sees on the display.
            if ((int) $this->input('tare_grams', 0) > 0) {
                $validator->errors()->add('tare_grams', __('Tare is handled by the scale itself — send the net weight in net_grams.'));
            }

            $this->validateMinimumWeight($validator, $item);
        });

        $validator->after(function ($validator) {
            $item = $this->menuItem();

            if (! $item) {
                return;
            }

            foreach ((array) $this->input('add_ons', []) as $i => $row) {
                if (! $item->addOns->contains('id', (int) ($row['id'] ?? 0))) {
                    $validator->errors()->add("add_ons.{$i}.id", __('Invalid add-on selected for :name.', ['name' => $item->name]));
                }
            }
        });
    }

    /**
     * The item's own minimum, enforced on the weight that was keyed.
     *
     * The wizard shows this live as you type, but that display is a
     * courtesy — this is the check that actually refuses the line, so the
     * limit holds for anything posting to the endpoint.
     */
    protected function validateMinimumWeight($validator, MenuItem $item): void
    {
        if (! $this->filled('net_grams') || ! $item->isPerKilo()) {
            return;
        }

        $net = (int) $this->input('net_grams');
        $minimum = (int) $item->min_weight_grams;

        // No step snapping: 437 g is a real fish, not a rounding error.
        if ($net >= $minimum) {
            return;
        }

        // What happens under the minimum is an admin decision, not a
        // constant — "Bill at minimum" lets the line through and the
        // recorder prices it at the minimum instead.
        if (! WeighedOrderSettings::current()->blocksBelowMinimum()) {
            return;
        }

        // Never suggests a smaller figure: the only way forward is a
        // heavier fish, so the message states the floor and stops there.
        $validator->errors()->add('net_grams', __(
            'The minimum for :item is :min g, but this reads :net g.',
            ['item' => $item->name, 'min' => $minimum, 'net' => $net],
        ));
    }

    public function isWeighed(): bool
    {
        return $this->input('line_type') === LineType::Weighed->value;
    }

    protected function menuItem(): ?MenuItem
    {
        if (! $this->filled('menu_item_id')) {
            return null;
        }

        return MenuItem::find($this->input('menu_item_id'));
    }

    /**
     * The client-generated UUID that makes a double-tapped "Add to Order"
     * safe. Absent on ordinary form posts, which is fine — those go
     * through a redirect, not a retryable fetch.
     */
    public function idempotencyKey(): ?string
    {
        $key = $this->header('Idempotency-Key') ?: $this->input('idempotency_key');

        return is_string($key) && preg_match('/^[0-9a-fA-F-]{36}$/', $key) ? strtolower($key) : null;
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
