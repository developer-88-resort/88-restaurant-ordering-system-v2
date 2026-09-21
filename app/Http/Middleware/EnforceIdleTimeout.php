<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs a Staff/Admin out after config('auth.pin.idle_timeout_minutes') with
 * no activity — shared tablets shouldn't stay signed in as whoever used them
 * last.
 *
 * The browser does the timing (resources/js/lib/idle-timeout.js) because it
 * sees taps and typing that never reach the server; this is the backstop
 * for a tab that was closed or asleep. "Activity" is any request except the
 * background heartbeat, which only counts when it says the person did
 * something (active=1) — that ping is also how a page that makes no requests
 * of its own, like the Kitchen Display, stays signed in.
 */
class EnforceIdleTimeout
{
    public const SESSION_KEY = 'idle.last_activity_at';

    /** Past the browser's own timer, so the browser always acts first. */
    protected const GRACE_SECONDS = 60;

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->usesPin()) {
            return $next($request);
        }

        $timeout = (int) config('auth.pin.idle_timeout_minutes') * 60 + self::GRACE_SECONDS;
        $lastActivity = $request->session()->get(self::SESSION_KEY);

        if ($lastActivity !== null && now()->timestamp - $lastActivity > $timeout) {
            return $this->signOut($request);
        }

        if ($lastActivity === null || ! $request->routeIs('heartbeat') || $request->boolean('active')) {
            $request->session()->put(self::SESSION_KEY, now()->timestamp);
        }

        return $next($request);
    }

    protected function signOut(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = __('You were signed out after :minutes minutes without activity.', [
            'minutes' => config('auth.pin.idle_timeout_minutes'),
        ]);

        if ($request->header('X-Inertia')) {
            $request->session()->flash('status', $message);

            return Inertia::location(route('login'));
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }

        return redirect()->route('login')->with('status', $message);
    }
}
