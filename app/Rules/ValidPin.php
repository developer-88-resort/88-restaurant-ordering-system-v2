<?php

namespace App\Rules;

use App\Models\User;
use App\Support\Pin;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A PIN someone chooses for themselves: 4–6 digits, not easy to guess, and
 * not already someone else's own PIN. A starting PIN handed out by a
 * Superadmin is looser — see starting().
 */
class ValidPin implements ValidationRule
{
    /**
     * @param  User|null  $owner  The account the PIN is for, so its own
     *                            current PIN doesn't count as "taken".
     * @param  bool  $startingPin  See starting().
     */
    public function __construct(protected ?User $owner = null, protected bool $startingPin = false) {}

    /**
     * The PIN a Superadmin hands out in person. Only the length is checked
     * here. An easy one (1234) is allowed, and the same one may be given to
     * several people, so handing over a new account stays simple: it is said
     * out loud once and lasts until the owner's first sign-in, where
     * setPin(temporary: true) makes them choose their own under the full
     * rules. Nothing is opened by a repeated PIN either — signing in is a
     * name first, then that name's PIN.
     */
    public static function starting(?User $owner = null): self
    {
        return new self($owner, startingPin: true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pin = (string) $value;

        if (! Pin::hasValidFormat($pin)) {
            $fail(__('The PIN must be :min to :max digits.', ['min' => Pin::MIN_LENGTH, 'max' => Pin::MAX_LENGTH]));

            return;
        }

        if ($this->startingPin) {
            return;
        }

        if ($weakness = Pin::weakness($pin)) {
            $fail($weakness);

            return;
        }

        // Only PINs their owners chose themselves are claimed. Starting PINs
        // are shared on purpose and are replaced at the owner's next sign-in,
        // so they never stand in anyone's way here.
        $taken = User::where('pin_lookup', Pin::lookup($pin))
            ->whereNotNull('pin_changed_at')
            ->when($this->owner?->exists, fn ($query) => $query->whereKeyNot($this->owner->getKey()))
            ->exists();

        if ($taken) {
            // Deliberately vague: saying "someone else uses this PIN" would
            // hand out a working PIN for one of the names on the sign-in screen.
            $fail(__('This PIN can\'t be used. Please choose a different one.'));
        }
    }
}
