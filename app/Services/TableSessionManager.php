<?php

namespace App\Services;

use App\Models\GuestSession;
use App\Models\Space;
use App\Models\SpaceSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Opens/reuses the QR "table dining session" and attributes each anonymous
 * device to its own guest seat within it.
 *
 * One active SpaceSession per table at a time; scanning the master QR (or
 * the shareable child QR) joins the current session. Each browser gets its
 * own GuestSession, remembered via an http-only cookie keyed to the table
 * session's public token — reopening the QR on the same phone resumes the
 * same guest instead of creating a duplicate, while a different phone at
 * the same table becomes the next guest number. Sessions close when the
 * table is released (Space goes back to Available) or by the safety
 * expiry, and closed sessions accept no further orders.
 */
class TableSessionManager
{
    /**
     * Safety net so an abandoned session can't stay joinable forever even
     * if the table's status is never touched.
     */
    protected const SESSION_LIFETIME_HOURS = 12;

    public static function activeSessionFor(Space $space): ?SpaceSession
    {
        return $space->sessions()
            ->where('status', 'active')
            ->whereNotNull('public_token')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('started_at')
            ->first();
    }

    public static function findOrOpenFor(Space $space): SpaceSession
    {
        return self::activeSessionFor($space) ?? $space->sessions()->create([
            'category_id' => $space->category_id,
            'status' => 'active',
            'public_token' => Str::random(40),
            'expires_at' => now()->addHours(self::SESSION_LIFETIME_HOURS),
        ]);
    }

    /**
     * The guest seat for this browser within this session — resumed from
     * the cookie when valid, freshly numbered otherwise. Queues the cookie
     * on the response either way, refreshing its lifetime.
     */
    public static function resolveGuest(Request $request, SpaceSession $session): GuestSession
    {
        $cookieName = self::cookieName($session);
        $token = $request->cookie($cookieName);

        $guest = $token
            ? $session->guestSessions()->where('public_token', $token)->where('status', 'active')->first()
            : null;

        if ($guest) {
            $guest->update(['last_active_at' => now()]);
        } else {
            $guest = DB::transaction(function () use ($session) {
                $nextNumber = ((int) $session->guestSessions()->lockForUpdate()->max('guest_number')) + 1;

                return $session->guestSessions()->create(['guest_number' => $nextNumber]);
            });
        }

        Cookie::queue(cookie(
            name: $cookieName,
            value: $guest->public_token,
            minutes: self::SESSION_LIFETIME_HOURS * 60,
            secure: null,
            httpOnly: true,
            sameSite: 'lax',
        ));

        return $guest;
    }

    /**
     * Cookie names are scoped per table session, so a phone that hops
     * between tables (or returns to the same table on a NEW session) never
     * bleeds a stale guest identity across sessions.
     */
    public static function cookieName(SpaceSession $session): string
    {
        return 'gs_'.substr($session->public_token, 0, 20);
    }
}
