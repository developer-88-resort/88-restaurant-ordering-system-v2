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
        $distinctRuleIds = array_values(array_unique($ruleIds));

        $rules = DiscountRule::currentlyAvailable()->whereIn('id', $distinctRuleIds)->get()->keyBy('id');

        if ($rules->count() !== count($distinctRuleIds)) {
            throw ValidationException::withMessages([
                'discounts' => __('One or more selected discounts are not available.'),
            ]);
        }

        // A discount that names a person (Senior, PWD, Diplomat) comes once
        // per person who claims it — two seniors are two rows, each with
        // their own name, ID and food. Any other discount comes once.
        foreach (array_count_values($ruleIds) as $ruleId => $times) {
            $rule = $rules[$ruleId];

            if ($times > 1 && ! $rule->requires_customer_id) {
                throw ValidationException::withMessages([
                    'discounts' => __('The same discount cannot be applied twice.'),
                ]);
            }
        }

        // …but the same person can't claim it twice.
        $claimedIds = [];
        foreach ($discountRows as $row) {
            $id = mb_strtolower(trim((string) ($row['id_number'] ?? '')));
            if ($id === '') {
                continue;
            }
            $key = ((int) $row['rule_id']).'|'.$id;
            if (isset($claimedIds[$key])) {
                throw ValidationException::withMessages([
                    'discounts' => __(':name was entered twice for ID :id.', ['name' => $rules[(int) $row['rule_id']]->name, 'id' => trim((string) $row['id_number'])]),
                ]);
            }
            $claimedIds[$key] = true;
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
            $totalPersons = null;
            $qualifiedPersons = null;

            if ($rule->scope === 'per_person') {
                // A share of the bill by headcount: bill ÷ persons in the
                // group, once per diplomat (e.g. 1 diplomat of 5 sharing
                // ₱5,000 → ₱1,000 eligible; 2 diplomats → two rows of
                // ₱1,000). A row may still say it covers several (an older
                // form sent one row with a count).
                $totalPersons = (int) ($row['total_persons'] ?? 0);
                $qualifiedPersons = (int) ($row['qualified_persons'] ?? 1);

                if ($totalPersons < 1 || $qualifiedPersons < 1) {
                    throw ValidationException::withMessages([
                        'discounts' => __(':name needs how many persons are in the group and how many qualify.', ['name' => $rule->name]),
                    ]);
                }

                if ($qualifiedPersons > $totalPersons) {
                    throw ValidationException::withMessages([
                        'discounts' => __(':name cannot cover more persons than are in the group.', ['name' => $rule->name]),
                    ]);
                }

                $eligibleAmount = self::shareOf($orderTotal, $qualifiedPersons, $totalPersons);
            } elseif ($rule->scope === 'eligible_items') {
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
                'total_persons' => $totalPersons,
                'qualified_persons' => $qualifiedPersons,
                'qualified_name' => $row['qualified_name'] ?? null,
                'id_number' => $row['id_number'] ?? null,
                'reason' => $row['reason'] ?? null,
            ];
        }

        // Every diplomat row of a group shares one headcount, and together
        // they can't be more people than the group.
        $perPerson = collect($resolved)->filter(fn ($entry) => $entry['rule']->scope === 'per_person')->groupBy(fn ($entry) => $entry['rule']->id);
        foreach ($perPerson as $entries) {
            if ($entries->pluck('total_persons')->unique()->count() > 1) {
                throw ValidationException::withMessages([
                    'discounts' => __(':name needs one headcount for the whole group.', ['name' => $entries->first()['rule']->name]),
                ]);
            }
            if ($entries->sum('qualified_persons') > $entries->first()['total_persons']) {
                throw ValidationException::withMessages([
                    'discounts' => __(':name cannot cover more persons than are in the group.', ['name' => $entries->first()['rule']->name]),
                ]);
            }
        }

        // A food line belongs to one Senior/PWD only — claimed twice, the
        // same VAT would come off it twice.
        $claimedItems = [];
        foreach ($resolved as $entry) {
            if (! $entry['rule']->isStatutory()) {
                continue;
            }
            foreach ($entry['item_ids'] as $itemId) {
                if (isset($claimedItems[$itemId])) {
                    throw ValidationException::withMessages([
                        'discounts' => __('An item can be claimed by one Senior/PWD only — pick each person\'s own food.'),
                    ]);
                }
                $claimedItems[$itemId] = true;
            }
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
                'total_persons' => $entry['total_persons'],
                'qualified_persons' => $entry['qualified_persons'],
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
     * $qualified of $persons equal shares of $total, rounded half-up to the
     * centavo (₱1,000.00 ÷ 3 × 1 → ₱333.33).
     */
    public static function shareOf(string $total, int $qualified, int $persons): string
    {
        $share = bcdiv(bcmul($total, (string) $qualified, 6), (string) $persons, 6);

        return bcadd($share, '0.005', 2);
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
