<?php

namespace App\Rules;

use App\Models\User;
use App\Support\Pin;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A PIN someone may choose: 4–6 digits, not easy to guess, and not already
 * held by another account.
 */
class ValidPin implements ValidationRule
{
    /**
     * @param  User|null  $owner  The account the PIN is for, so its own
     *                            current PIN doesn't count as "taken".
     */
    public function __construct(protected ?User $owner = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pin = (string) $value;

        if (! Pin::hasValidFormat($pin)) {
            $fail(__('The PIN must be :min to :max digits.', ['min' => Pin::MIN_LENGTH, 'max' => Pin::MAX_LENGTH]));

            return;
        }

        if ($weakness = Pin::weakness($pin)) {
            $fail($weakness);

            return;
        }

        $taken = User::where('pin_lookup', Pin::lookup($pin))
            ->when($this->owner?->exists, fn ($query) => $query->whereKeyNot($this->owner->getKey()))
            ->exists();

        if ($taken) {
            // Deliberately vague: saying "someone else uses this PIN" would
            // hand out a working PIN for one of the names on the sign-in screen.
            $fail(__('This PIN can\'t be used. Please choose a different one.'));
        }
    }
}
