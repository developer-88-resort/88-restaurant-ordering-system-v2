<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Counts wrong PINs, locks a name after too many, and writes both to the
 * Audit Logs. Shared by the sign-in screen and by manager approvals, so a
 * manager's PIN can't be guessed through a cancel dialog instead.
 */
class PinAttempts
{
    public static function ensureNotLocked(User $user, string $ip, string $errorKey): void
    {
        $limits = [
            self::userKey($user) => (int) config('auth.pin.max_attempts'),
            self::deviceKey($ip) => (int) config('auth.pin.device_max_attempts'),
        ];

        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw ValidationException::withMessages([
                    $errorKey => __('Too many wrong PINs. Try again in :minutes min.', [
                        'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60),
                    ]),
                ]);
            }
        }
    }

    /**
     * @param  string  $context  'sign_in' or 'approval'
     */
    public static function recordFailure(User $user, string $ip, string $context): void
    {
        $decay = (int) config('auth.pin.lockout_seconds');
        $userAttempts = RateLimiter::hit(self::userKey($user), $decay);
        $deviceAttempts = RateLimiter::hit(self::deviceKey($ip), $decay);

        activity('audit')
            ->performedOn($user)
            ->event('failed_pin_login')
            ->withProperties(['ip' => $ip, 'attempt' => $userAttempts, 'context' => $context])
            ->log($context === 'approval'
                ? "Wrong manager PIN entered for {$user->name} during an approval."
                : "Wrong PIN entered for {$user->name}.");

        $minutes = (int) ceil($decay / 60);

        if ($userAttempts === (int) config('auth.pin.max_attempts')) {
            activity('audit')
                ->performedOn($user)
                ->event('pin_lockout')
                ->withProperties(['ip' => $ip, 'scope' => 'name', 'context' => $context])
                ->log("PIN sign-in for {$user->name} locked for {$minutes} minutes after {$userAttempts} wrong PINs.");
        }

        if ($deviceAttempts === (int) config('auth.pin.device_max_attempts')) {
            activity('audit')
                ->event('pin_lockout')
                ->withProperties(['ip' => $ip, 'scope' => 'device', 'context' => $context])
                ->log("PIN sign-in from {$ip} paused for {$minutes} minutes after {$deviceAttempts} wrong PINs across names.");
        }
    }

    public static function clear(User $user): void
    {
        RateLimiter::clear(self::userKey($user));
    }

    protected static function userKey(User $user): string
    {
        return 'pin-attempts:user:'.$user->getKey();
    }

    protected static function deviceKey(string $ip): string
    {
        return 'pin-attempts:device:'.$ip;
    }
}
