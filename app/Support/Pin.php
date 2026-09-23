<?php

namespace App\Support;

/**
 * The rules for what a sign-in PIN may be, and the keyed fingerprint that
 * keeps every PIN unique.
 */
class Pin
{
    public const MIN_LENGTH = 4;

    public const MAX_LENGTH = 6;

    /**
     * Frequently chosen PINs that the shape checks below don't already catch
     * (keypad columns and diagonals, 1004, 2000, ...).
     */
    protected const COMMON = [
        '1004', '2000', '2001', '2580', '0852', '1357', '2468', '5683', '6969', '1379', '3697', '7410', '0147', '9630',
        '147258', '258369', '159753', '123321', '321123', '696969', '520520', '147369', '789456', '456789',
    ];

    /**
     * An HMAC of the PIN under a key derived from APP_KEY: stored in
     * users.pin_lookup (indexed), so checking whether a PIN is already
     * someone's own is a single indexed lookup rather than a bcrypt check
     * against every user. Not unique since 2026-09-23 — several accounts may
     * share a starting PIN (see App\Rules\ValidPin::starting()).
     * Sign-in itself never uses this — it checks the bcrypt pin_hash.
     *
     * Rotating APP_KEY changes every fingerprint; PINs keep working (bcrypt),
     * only the uniqueness check would stop seeing the old ones.
     */
    public static function lookup(string $pin): string
    {
        return hash_hmac('sha256', $pin, hash_hmac('sha256', 'users.pin_lookup', (string) config('app.key')));
    }

    public static function hasValidFormat(string $pin): bool
    {
        return preg_match('/^\d{'.self::MIN_LENGTH.','.self::MAX_LENGTH.'}$/', $pin) === 1;
    }

    /**
     * Why this PIN is too easy to guess, or null when it's fine.
     */
    public static function weakness(string $pin): ?string
    {
        if (count(array_unique(str_split($pin))) === 1) {
            return __('Don\'t use the same digit over and over (like 0000).');
        }

        if (str_contains('01234567890', $pin) || str_contains('09876543210', $pin)) {
            return __('Don\'t use digits in a row (like 1234 or 4321).');
        }

        if (self::isRepeatedChunk($pin) || self::isDoubledDigits($pin)) {
            return __('Don\'t use a repeating pattern (like 1212 or 1122).');
        }

        if (in_array($pin, self::COMMON, true)) {
            return __('That PIN is too common. Choose one that is harder to guess.');
        }

        return null;
    }

    /** 1212, 123123, 121212: one short chunk repeated to fill the PIN. */
    protected static function isRepeatedChunk(string $pin): bool
    {
        $length = strlen($pin);

        foreach ([1, 2, 3] as $chunk) {
            if ($length > $chunk && $length % $chunk === 0 && str_repeat(substr($pin, 0, $chunk), $length / $chunk) === $pin) {
                return true;
            }
        }

        return false;
    }

    /** 1122, 112233: every digit written twice. */
    protected static function isDoubledDigits(string $pin): bool
    {
        if (strlen($pin) % 2 !== 0) {
            return false;
        }

        foreach (str_split($pin, 2) as $pair) {
            if ($pair[0] !== $pair[1]) {
                return false;
            }
        }

        return true;
    }
}
