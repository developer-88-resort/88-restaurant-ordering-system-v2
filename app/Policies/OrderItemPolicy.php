<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\OrderItemCanceller;

/**
 * Who may take a line off an order — from the Kitchen Display or from
 * Order Management, the rule is the same.
 *
 * Every operational role can cancel: the waiter hearing "we don't want the
 * sisig anymore" and the cook finding the last squid gone are both Staff
 * here, and neither should have to go looking for a manager while the
 * dish is still only a ticket. Once the food is Ready or Served, or the
 * bill is already paid, removing the charge is a manager's call — an
 * Admin/Superadmin approves their own, Staff need one to sign it off.
 */
class OrderItemPolicy
{
    public function cancel(User $user, OrderItem $item): bool
    {
        return $user->is_active
            && in_array($user->role, [UserRole::Superadmin, UserRole::Admin, UserRole::Staff], true)
            && $item->order->status !== OrderStatus::Cancelled;
    }

    public function cancelWithoutApproval(User $user, OrderItem $item): bool
    {
        return $this->cancel($user, $item)
            && ($user->isManager() || ! OrderItemCanceller::requiresApproval($item->order));
    }
}
