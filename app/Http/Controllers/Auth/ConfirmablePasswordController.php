<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConfirmablePasswordController extends Controller
{
    /**
     * Show the confirm password view.
     *
     * Deliberately kept as a classic Blade page, not Inertia: the
     * `password.confirm` middleware can transparently redirect here from
     * *any* sensitive action anywhere in the still-mostly-Blade app (e.g.
     * Superadmin > User Management's "Invite User"), so the entry point is
     * unpredictable — unlike Dashboard/Menu Management, which are only ever
     * reached via their own already-`data-turbo="false"`-guarded links.
     * Turbo (loaded app-wide) can't be taught in advance about every future
     * link that might hit this gate, so keeping this single narrow-purpose
     * form on Blade removes the whole risk category instead of chasing it
     * link by link. Confirmed via the "Invite User" white-screen bug it
     * originally caused.
     */
    public function show(): View
    {
        return view('auth.confirm-password');
    }

    /**
     * Confirm the user's password.
     */
    public function store(Request $request): RedirectResponse
    {
        if (! Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route($request->user()->homeRouteName(), absolute: false));
    }
}
