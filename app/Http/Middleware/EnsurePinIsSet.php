<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * A Staff/Admin with no PIN yet (an account from before PIN sign-in, signed
 * in with email) or with an admin-given PIN goes to choose their own PIN
 * before any other page — where they were headed is kept for after.
 */
class EnsurePinIsSet
{
    /** Reachable while a PIN is still owed. */
    protected const ALLOWED_ROUTES = ['pin.setup', 'pin.setup.store', 'logout', 'heartbeat', 'locale.update'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->mustSetPin() || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        if ($request->header('X-Inertia')) {
            return Inertia::location(route('pin.setup'));
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('Set up your PIN first.')], 409);
        }

        return redirect()->guest(route('pin.setup'));
    }
}
