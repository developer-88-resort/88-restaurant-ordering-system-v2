<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    /**
     * Show the "set your password" form for a valid, unexpired invitation
     * link. Invalid, expired, or already-used links bounce back to login
     * with a clear explanation rather than a confusing 404.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $this->findValidInvitation(
            $request->string('email')->toString(),
            $request->string('token')->toString()
        );

        if (! $user) {
            return redirect()->route('login')
                ->with('error', __('This invitation link is invalid or has expired. Please ask a Superadmin to resend your invitation.'));
        }

        return Inertia::render('Auth/AcceptInvitation', [
            'token' => $request->string('token')->toString(),
            'email' => $user->email,
        ]);
    }

    /**
     * Activate the account: the invitee's own password is the only thing
     * being set here — no Superadmin ever sees or chooses it.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $this->findValidInvitation(
            $request->string('email')->toString(),
            $request->string('token')->toString()
        );

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => __('This invitation link is invalid or has expired. Please ask a Superadmin to resend your invitation.'),
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
            'invitation_token' => null,
            'invitation_expires_at' => null,
            'email_verified_at' => now(),
        ])->save();

        return redirect()->route('login')
            ->with('status', __('Your account is now active. You can sign in below.'));
    }

    /**
     * Resolve the invited user for a given email + raw token, or null if
     * the link is unknown, already used, or expired. The token is stored
     * hashed, so — like Laravel's own password broker — lookup is by email
     * first, then the raw token is verified against the stored hash.
     */
    protected function findValidInvitation(string $email, string $token): ?User
    {
        $user = User::where('email', $email)->whereNull('password')->first();

        if (! $user || ! $user->invitation_token) {
            return null;
        }

        if ($user->invitation_expires_at && $user->invitation_expires_at->isPast()) {
            return null;
        }

        if (! Hash::check($token, $user->invitation_token)) {
            return null;
        }

        return $user;
    }
}
