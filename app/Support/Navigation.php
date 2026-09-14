<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Resolves config/navigation.php into the sidebar a given user actually
 * sees on the CURRENT request — permission-filtered, translated, and with
 * the active route highlighted.
 *
 * Called from both render stacks (layouts/app.blade.php for Blade pages,
 * HandleInertiaRequests for Inertia ones) against the identical Laravel
 * request/route context, so the two can never show a different menu for
 * the same user. That guarantee is what the nav parity test checks.
 *
 * `badge` is left as the KEY from config (e.g. 'pending_orders'), not a
 * resolved number: each render stack already has its own live counter for
 * that figure (Alpine's global `pendingOrdersCount` on the Blade side,
 * React state fed by the same Echo channel on the other) and maps the key
 * to it locally, so the sidebar badge keeps updating in real time on both
 * stacks instead of freezing at whatever it was when the page loaded.
 */
class Navigation
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $groups = [];

        foreach (config('navigation.groups', []) as $group) {
            if (! self::allowed($user, $group['permission'] ?? null)) {
                continue;
            }

            $items = self::resolveItems($group['items'] ?? [], $user);

            if ($items === []) {
                continue;
            }

            $groups[] = [
                'key' => $group['key'],
                'label' => __($group['label']),
                'items' => $items,
            ];
        }

        return $groups;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected static function resolveItems(array $items, User $user): array
    {
        $resolved = [];

        foreach ($items as $item) {
            if (! self::allowed($user, $item['permission'] ?? null)) {
                continue;
            }

            $resolved[] = [
                'key' => $item['key'],
                'label' => __($item['label']),
                'icon' => $item['icon'],
                'href' => route($item['route']),
                'active' => request()->routeIs(...$item['active']),
                'inertia' => $item['inertia'] ?? false,
                'badge' => $item['badge'] ?? null,
                'children' => self::resolveItems($item['children'] ?? [], $user),
            ];
        }

        return $resolved;
    }

    protected static function allowed(User $user, ?string $permission): bool
    {
        return match ($permission) {
            null => in_array($user->role, [UserRole::Superadmin, UserRole::Admin, UserRole::Staff], true),
            'superadmin' => $user->role === UserRole::Superadmin,
            'reports' => in_array($user->role, [UserRole::Superadmin, UserRole::Admin], true),
            'promotions' => in_array($user->role, [UserRole::Superadmin, UserRole::Admin], true),
            default => $user->can($permission),
        };
    }
}
