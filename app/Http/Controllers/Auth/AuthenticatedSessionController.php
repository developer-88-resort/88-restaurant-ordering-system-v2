<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\AvailableLocales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): SymfonyResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Carry the language chosen on the login page (before this user was
        // known) into their saved account preference, so it also follows
        // them if they next sign in from a browser with no cookie yet.
        $cookieLocale = $request->cookie('locale');
        if ($cookieLocale && in_array($cookieLocale, AvailableLocales::CODES, true)) {
            $request->user()->update(['locale' => $cookieLocale]);
        }

        $redirectTo = match ($request->user()->role) {
            UserRole::Superadmin => route('superadmin.dashboard', absolute: false),
            default => route('profile.edit', absolute: false),
        };

        $response = redirect()->intended($redirectTo)->with('status', __('Signed in successfully.'));

        // Inertia's client makes this login submission as an XHR request and
        // can't follow a normal 3xx redirect into a page it doesn't manage —
        // some destinations here (the Superadmin dashboard, for now) are
        // still classic Blade views, not yet converted to Inertia.
        // Inertia::location() forces a real full-page browser navigation
        // instead, which works correctly for both Inertia and Blade targets.
        return Inertia::location($response->getTargetUrl());
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('You have been signed out successfully.'));
    }
}
