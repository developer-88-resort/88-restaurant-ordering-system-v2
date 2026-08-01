<?php

namespace App\Services;

use App\Enums\DiscountCalculationMode;
use App\Enums\UserRole;
use App\Models\DiscountRule;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Turns the checkout form's selected discount rules into fully validated,
 * server-priced discount lines. Every business rule lives here, on the
 * backend — the client's checklist/preview is advisory only:
 *
 *  - rule must exist, be active, and be inside its date window
 *  - custom rules require a cashier-entered value (validated per mode)
 *  - stacking: combining any non-stackable rule with another discount is a
 *    conflict, allowed only with manager approval (recorded)
 *  - rules flagged requires_manager_approval always need an approver
 *  - a staff member's "manager approval" means an active admin/superadmin
 *    re-entering their credentials; admins approve their own actions
 *  - per-rule minimum bill, required ID/reason, item eligibility, and
 *    maximum-discount caps are all enforced
 */
class CheckoutDiscountResolver
{
    /**
     * @param  array<int, array<string, mixed>>  $discountRows  Validated request rows: rule_id, entered_value?, qualified_name?, id_number?, reason?, item_ids?, eligible_amount?
     * @return array{lines: array<int, array<string, mixed>>, records: array<int, array<string, mixed>>, eligible_item_ids: array<int>}
     */
    public static function resolve(Order $order, array $discountRows, User $actingUser, ?string $managerEmail = null, ?string $managerPassword = null): array
    {
        if ($discountRows === []) {
            return ['lines' => [], 'records' => [], 'eligible_item_ids' => []];
        }

        $ruleIds = array_map(fn ($row) => (int) $row['rule_id'], $discountRows);

        if (count($ruleIds) !== count(array_unique($ruleIds))) {
            throw ValidationException::withMessages([
                'discounts' => __('The same discount cannot be applied twice.'),
            ]);
        }

        $rules = DiscountRule::currentlyAvailable()->whereIn('id', $ruleIds)->get()->keyBy('id');

        if ($rules->count() !== count($ruleIds)) {
            throw ValidationException::withMessages([
                'discounts' => __('One or more selected discounts are not available.'),
            ]);
        }

        $orderTotal = (string) $order->total_amount;
        $needsManagerApproval = false;

        // Combining anything with a non-stackable rule is a conflict the
        // cashier can't authorize alone.
        if (count($discountRows) > 1 && $rules->contains(fn (DiscountRule $rule) => ! $rule->is_stackable)) {
            $needsManagerApproval = true;
        }

        $resolved = [];

        foreach ($discountRows as $row) {
            $rule = $rules[(int) $row['rule_id']];

            if ($rule->min_bill_amount !== null && bccomp($orderTotal, (string) $rule->min_bill_amount, 2) < 0) {
                throw ValidationException::withMessages([
                    'discounts' => __(':name requires a minimum bill of ₱:amount.', [
                        'name' => $rule->name,
                        'amount' => number_format((float) $rule->min_bill_amount, 2),
                    ]),
                ]);
            }

            $enteredValue = isset($row['entered_value']) && $row['entered_value'] !== '' && $row['entered_value'] !== null
                ? (string) $row['entered_value']
                : null;

            if ($rule->is_custom_value) {
                if ($enteredValue === null || bccomp($enteredValue, '0', 2) <= 0) {
                    throw ValidationException::withMessages([
                        'discounts' => __(':name needs a discount value.', ['name' => $rule->name]),
                    ]);
                }

                if ($rule->calculation_mode === DiscountCalculationMode::Percent && bccomp($enteredValue, '100', 2) > 0) {
                    throw ValidationException::withMessages([
                        'discounts' => __(':name cannot exceed 100%.', ['name' => $rule->name]),
                    ]);
                }
            }

            $value = $enteredValue ?? (string) $rule->value;

            if ($value === '' || $value === null) {
                throw ValidationException::withMessages([
                    'discounts' => __(':name has no discount value configured.', ['name' => $rule->name]),
                ]);
            }

            if ($rule->requires_customer_id && (empty($row['qualified_name']) || empty($row['id_number']))) {
                throw ValidationException::withMessages([
                    'discounts' => __(':name requires the customer name and ID number.', ['name' => $rule->name]),
                ]);
            }

            if ($rule->requires_reason && empty($row['reason'])) {
                throw ValidationException::withMessages([
                    'discounts' => __(':name requires a reason.', ['name' => $rule->name]),
                ]);
            }

            if ($rule->requires_manager_approval) {
                $needsManagerApproval = true;
            }

            // Resolve the eligible base. Item-scope rules take a selected
            // item list (priced from the live order lines, net of any
            // cancellations — never from client-sent amounts) or an
            // explicit eligible amount; whole-bill rules cover everything.
            $eligibleAmount = null;
            $itemIds = [];

            if ($rule->scope === 'eligible_items') {
                $itemIds = array_map('intval', $row['item_ids'] ?? []);

                if ($itemIds !== []) {
                    $items = $order->items->whereIn('id', $itemIds);

                    if ($items->count() !== count(array_unique($itemIds))) {
                        throw ValidationException::withMessages([
                            'discounts' => __('One or more selected items do not belong to this order.'),
                        ]);
                    }

                    $eligibleAmount = '0.00';
                    foreach ($items as $item) {
                        $eligibleAmount = bcadd($eligibleAmount, $item->lineTotalNet(), 2);
                    }
                } elseif (isset($row['eligible_amount']) && $row['eligible_amount'] !== '' && $row['eligible_amount'] !== null) {
                    $eligibleAmount = (string) $row['eligible_amount'];
                } else {
                    throw ValidationException::withMessages([
                        'discounts' => __(':name needs eligible items or an eligible amount.', ['name' => $rule->name]),
                    ]);
                }

                if (bccomp($eligibleAmount, $orderTotal, 2) > 0) {
                    throw ValidationException::withMessages([
                        'discounts' => __('The eligible amount for :name cannot exceed the order total.', ['name' => $rule->name]),
                    ]);
                }
            } elseif ($rule->isStatutory()) {
                // A statutory rule configured as whole-bill still needs an
                // explicit base for the VAT-exemption math.
                $eligibleAmount = $orderTotal;
            }

            $resolved[] = [
                'rule' => $rule,
                'value' => $value,
                'eligible_amount' => $eligibleAmount,
                'item_ids' => $itemIds,
                'qualified_name' => $row['qualified_name'] ?? null,
                'id_number' => $row['id_number'] ?? null,
                'reason' => $row['reason'] ?? null,
            ];
        }

        // Statutory eligible bases all leave the same pool — together they
        // can't exceed the bill.
        $statutoryTotal = '0.00';
        foreach ($resolved as $entry) {
            if ($entry['rule']->isStatutory() && $entry['eligible_amount'] !== null) {
                $statutoryTotal = bcadd($statutoryTotal, $entry['eligible_amount'], 2);
            }
        }
        if (bccomp($statutoryTotal, $orderTotal, 2) > 0) {
            throw ValidationException::withMessages([
                'discounts' => __('Combined Senior/PWD eligible amounts cannot exceed the order total.'),
            ]);
        }

        $approvedBy = $needsManagerApproval
            ? self::resolveApprover($actingUser, $managerEmail, $managerPassword)
            : null;

        // Priority (ascending) decides calculation order among stacked
        // discounts — the calculator keeps statutory / percent / fixed in
        // their own passes, so this settles ordering within each pass.
        usort($resolved, fn ($a, $b) => $a['rule']->priority <=> $b['rule']->priority);

        $lines = [];
        $records = [];
        $eligibleItemIds = [];

        foreach ($resolved as $entry) {
            $rule = $entry['rule'];

            $lines[] = [
                'calculation_mode' => $rule->calculation_mode->value,
                'value' => $entry['value'],
                'statutory_type' => $rule->statutory_type?->value,
                'eligible_amount' => $entry['eligible_amount'],
                'max_discount_amount' => $rule->max_discount_amount !== null ? (string) $rule->max_discount_amount : null,
            ];

            $records[] = [
                'discount_rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'rule_code' => $rule->code,
                'calculation_mode' => $rule->calculation_mode->value,
                'entered_value' => $entry['value'],
                'statutory_type' => $rule->statutory_type?->value,
                'eligible_amount' => $entry['eligible_amount'] ?? $orderTotal,
                'qualified_name' => $entry['qualified_name'],
                'id_number' => $entry['id_number'],
                'reason' => $entry['reason'],
                'applied_by' => $actingUser->id,
                'approved_by' => $approvedBy?->id,
            ];

            $eligibleItemIds = array_merge($eligibleItemIds, $entry['item_ids']);
        }

        return [
            'lines' => $lines,
            'records' => $records,
            'eligible_item_ids' => array_values(array_unique($eligibleItemIds)),
        ];
    }

    /**
     * Who is authorizing this? Admins/superadmins carry their own
     * authority; staff must have an active admin/superadmin re-enter
     * their credentials, and that manager is recorded as the approver.
     */
    public static function resolveApprover(User $actingUser, ?string $managerEmail, ?string $managerPassword): User
    {
        if (in_array($actingUser->role, [UserRole::Superadmin, UserRole::Admin], true)) {
            return $actingUser;
        }

        if (! $managerEmail || ! $managerPassword) {
            throw ValidationException::withMessages([
                'manager_email' => __('Manager approval is required for this action.'),
            ]);
        }

        $manager = User::where('email', $managerEmail)
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Superadmin->value, UserRole::Admin->value])
            ->first();

        if (! $manager || ! Hash::check($managerPassword, $manager->password)) {
            throw ValidationException::withMessages([
                'manager_email' => __('Manager credentials are invalid.'),
            ]);
        }

        return $manager;
    }
}
