<?php

namespace App\Support;

use App\Models\User;
use App\Services\CheckoutDiscountResolver;
use Illuminate\Validation\ValidationException;

/**
 * The manager sign-off for cancelling a line that is already Ready,
 * Served, or paid for.
 *
 * Its own seam rather than a direct call into CheckoutDiscountResolver, so
 * the item-cancel approval can change how a manager proves who they are
 * without touching the checkout's approval path.
 */
class ManagerApproval
{
    /**
     * The approving manager: the acting user themself when they are one,
     * otherwise whoever proved it — a manager picked from the list plus
     * their PIN (Admins may have no password now), or email + password.
     *
     * @param  array<string, mixed>  $data  Validated request data carrying manager_id / manager_pin, or manager_email / manager_password.
     */
    public static function resolve(User $actingUser, array $data): User
    {
        if (! $actingUser->isManager() && ! empty($data['manager_id'])) {
            return self::byPin((int) $data['manager_id'], (string) ($data['manager_pin'] ?? ''));
        }

        return CheckoutDiscountResolver::resolveApprover(
            $actingUser,
            $data['manager_email'] ?? null,
            $data['manager_password'] ?? null,
        );
    }

    /**
     * Managers who can approve with a PIN, for the approval dialogs' picker.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function pinApprovers(): array
    {
        return User::approvesWithPin()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->all();
    }

    protected static function byPin(int $managerId, string $pin): User
    {
        $manager = User::approvesWithPin()->find($managerId);

        if (! $manager) {
            throw ValidationException::withMessages([
                'manager_pin' => __('Choose a manager to approve this.'),
            ]);
        }

        // The same wrong-PIN counter as the sign-in screen: a manager's PIN
        // can't be guessed here instead.
        PinAttempts::ensureNotLocked($manager, request()->ip(), 'manager_pin');

        if (! $manager->checkPin($pin)) {
            PinAttempts::recordFailure($manager, request()->ip(), 'approval');
            PinAttempts::ensureNotLocked($manager, request()->ip(), 'manager_pin');

            throw ValidationException::withMessages([
                'manager_pin' => __('Manager PIN is not correct.'),
            ]);
        }

        PinAttempts::clear($manager);

        return $manager;
    }
}
