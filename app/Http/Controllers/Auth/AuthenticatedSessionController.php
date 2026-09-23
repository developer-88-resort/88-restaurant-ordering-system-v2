<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\CompletesSignIn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\Pin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    use CompletesSignIn;

    /**
     * Display the login view: the name + PIN pad for Staff/Admin, with the
     * email + password form one tap away (always for Superadmin).
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'pinUsers' => User::signsInWithPin()
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'initials' => $user->initials(),
                    'avatar_url' => $user->avatarUrl(),
                    'role_label' => $user->role->label(),
                ])
                ->values(),
            'pinLength' => ['min' => Pin::MIN_LENGTH, 'max' => Pin::MAX_LENGTH],
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): SymfonyResponse
    {
        $request->authenticate();

        return $this->completeSignIn($request);
    }

    /**
     * Destroy an authenticated session. `reason` only changes the message:
     * "switch" (Switch user on a shared tablet) and "idle" (signed out by
     * the inactivity timer).
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        $status = match ($request->input('reason')) {
            'switch' => __('Signed out. Tap your name to sign in.'),
            'idle' => __('You were signed out after :minutes minutes without activity.', [
                'minutes' => config('auth.pin.idle_timeout_minutes'),
            ]),
            default => __('You have been signed out successfully.'),
        };

        return redirect()->route('login')->with('status', $status);
    }
}
