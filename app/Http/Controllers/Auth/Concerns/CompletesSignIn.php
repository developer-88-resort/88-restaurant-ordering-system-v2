<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AvailableLocales;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

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

        $response = redirect()->to(static::intendedUrlFor($request->user()))
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

    /**
     * Where this person was headed before being asked to sign in — but only
     * if they may actually open it. A browser that was bounced off a
     * Superadmin page keeps that page in the session, and the next person to
     * sign in on it (a Staff member setting up their PIN, say) would land on
     * a bare 403. They get their own home page instead.
     */
    protected static function intendedUrlFor(User $user): string
    {
        $intended = session()->pull('url.intended');

        return $intended && static::mayOpen($user, $intended)
            ? $intended
            : static::signedInHome($user);
    }

    protected static function mayOpen(User $user, string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($host && $host !== request()->getHost()) {
            return false;
        }

        try {
            $route = app('router')->getRoutes()->match(
                Request::create(parse_url($url, PHP_URL_PATH) ?: '/', 'GET')
            );
        } catch (Throwable) {
            // Not a page this app serves with a GET — nothing to go back to.
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'role:')) {
                $allowed = explode(',', substr($middleware, strlen('role:')));

                if (! in_array($user->role->value, $allowed, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
