<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\PinAttempts;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Sign-in by tapping a name and entering that person's PIN.
 */
class PinLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
            'pin' => ['required', 'string', 'max:12'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        // Only a name that is on the sign-in screen can be signed into this
        // way — never a Superadmin, a deactivated account, or one without a PIN.
        $user = User::signsInWithPin()->find($this->integer('user_id'));

        if (! $user) {
            throw ValidationException::withMessages([
                'pin' => __('That name can\'t sign in with a PIN. Choose your name again, or sign in with email.'),
            ]);
        }

        PinAttempts::ensureNotLocked($user, $this->ip(), 'pin');

        if (! $user->checkPin((string) $this->input('pin'))) {
            PinAttempts::recordFailure($user, $this->ip(), 'sign_in');
            PinAttempts::ensureNotLocked($user, $this->ip(), 'pin');

            throw ValidationException::withMessages([
                'pin' => __('Wrong PIN. Please try again.'),
            ]);
        }

        PinAttempts::clear($user);

        Auth::login($user);
    }
}
