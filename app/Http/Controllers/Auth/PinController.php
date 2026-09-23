<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\CompletesSignIn;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\ValidPin;
use App\Support\Pin;
use App\Support\PinAttempts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * A Staff/Admin choosing their own PIN: the forced first-time setup (no PIN
 * yet, or still an admin-given one) and the Change PIN form on the profile.
 */
class PinController extends Controller
{
    use CompletesSignIn;

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->mustSetPin()) {
            return redirect()->route('profile.edit');
        }

        return Inertia::render('Auth/SetPin', [
            'replacingTemporaryPin' => $user->hasPin(),
            'pinLength' => ['min' => Pin::MIN_LENGTH, 'max' => Pin::MAX_LENGTH],
        ]);
    }

    public function store(Request $request): SymfonyResponse
    {
        $user = $request->user();

        abort_unless($user->mustSetPin(), 403);

        $validated = $request->validate([
            'pin' => ['required', 'string', 'confirmed', new ValidPin($user)],
        ]);

        if ($user->checkPin($validated['pin'])) {
            throw ValidationException::withMessages([
                'pin' => __('Choose a new PIN — not the one you were given.'),
            ]);
        }

        $firstPin = ! $user->hasPin();
        $user->setPin($validated['pin']);

        activity('audit')
            ->causedBy($user)
            ->performedOn($user)
            ->event('pin_set')
            ->log($firstPin ? "{$user->name} set up their PIN." : "{$user->name} replaced the PIN an admin gave them.");

        $response = redirect()->intended(static::signedInHome($user))
            ->with('status', __('Your PIN is set. Use it with your name to sign in.'));

        return Inertia::location($response->getTargetUrl());
    }

    /**
     * Change PIN from Account Settings: prove the current one first.
     */
    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->usesPin() && $user->hasPin(), 403);

        $validated = $request->validate([
            'current_pin' => ['required', 'string'],
            'pin' => ['required', 'string', 'confirmed', 'different:current_pin', new ValidPin($user)],
        ]);

        PinAttempts::ensureNotLocked($user, $request->ip(), 'current_pin');

        if (! $user->checkPin($validated['current_pin'])) {
            PinAttempts::recordFailure($user, $request->ip(), 'change_pin');

            throw ValidationException::withMessages(['current_pin' => __('Your current PIN is not correct.')]);
        }

        PinAttempts::clear($user);
        $user->setPin($validated['pin']);

        activity('audit')
            ->causedBy($user)
            ->performedOn($user)
            ->event('pin_changed')
            ->log("{$user->name} changed their PIN.");

        return back()->with('status', __('PIN updated successfully.'));
    }
}
