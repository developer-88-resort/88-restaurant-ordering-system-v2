<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Support\StatusPage;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maintenance Mode (Settings → Maintenance Mode). While it is on, everyone
 * but a Superadmin — Admin, Staff, and customers at a table QR — gets the
 * "Website under maintenance" page. Someone already signed in stays signed
 * in and simply sees the page on their next click, so switching it off lets
 * them carry on.
 *
 * Signing in stays open so a Superadmin can still get in: /login shows the
 * maintenance page too, with a small "Superadmin sign-in" link to the form
 * (?signin=1). The printer bridge's /api routes are not in the web group, so
 * kitchen printing is unaffected.
 */
class ShowMaintenancePage
{
    /** Paths that keep working: signing in and out, and the status page. */
    protected const OPEN_PATHS = ['login', 'login/pin', 'logout', 'locale', 'up', 'broadcasting/auth', 'status'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! Setting::maintenanceIsOn() || $request->user()?->role === UserRole::Superadmin) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        $isSignInForm = $path === 'login' && $request->isMethod('GET') && $request->boolean('signin');
        if ($isSignInForm || ($path !== 'login' && in_array($path, self::OPEN_PATHS, true)) || ($path === 'login' && ! $request->isMethod('GET'))) {
            return $next($request);
        }

        // An Inertia visit would show this page inside its error modal, so
        // ask for a full page load of the same address instead.
        if ($request->header('X-Inertia')) {
            return Inertia::location($request->fullUrl());
        }

        $message = Setting::query()->whereKey(1)->value('maintenance_message');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message ?: __('This website is currently undergoing scheduled maintenance. We should be back shortly.')], 503);
        }

        return response()->view('maintenance', [
            'message' => $message,
            'showSignInLink' => $path === 'login',
            'status' => StatusPage::build(),
        ], 503)->header('Retry-After', '60');
    }
}
