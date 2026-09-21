<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AvailableLocales;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * What happens once someone has proven who they are — shared by the email +
 * password form and the name + PIN pad, so both land the same way.
 */
trait CompletesSignIn
{
    protected function completeSignIn(Request $request): SymfonyResponse
    {
        $request->session()->regenerate();

        // Carry the language chosen on the login page (before this user was
        // known) into their saved account preference, so it also follows
        // them if they next sign in from a browser with no cookie yet.
        $cookieLocale = $request->cookie('locale');
        if ($cookieLocale && in_array($cookieLocale, AvailableLocales::CODES, true)) {
            $request->user()->update(['locale' => $cookieLocale]);
        }

        // No PIN yet (an existing account's first sign-in since PINs) or
        // still the one an admin gave them: choose their own first. Where
        // they were headed stays in the session for after that.
        if ($request->user()->mustSetPin()) {
            return Inertia::location(route('pin.setup'));
        }

        $response = redirect()->intended(static::signedInHome($request->user()))
            ->with('status', __('Signed in successfully.'));

        // Inertia's client makes this login submission as an XHR request and
        // can't follow a normal 3xx redirect into a page it doesn't manage —
        // some destinations here (the Superadmin dashboard, for now) are
        // still classic Blade views, not yet converted to Inertia.
        // Inertia::location() forces a real full-page browser navigation
        // instead, which works correctly for both Inertia and Blade targets.
        return Inertia::location($response->getTargetUrl());
    }

    protected static function signedInHome(User $user): string
    {
        return match ($user->role) {
            UserRole::Superadmin => route('superadmin.dashboard', absolute: false),
            default => route('profile.edit', absolute: false),
        };
    }
}
