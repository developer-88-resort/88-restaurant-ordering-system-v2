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
 * the same table becomes the next guest number.
 *
 * The cookie is only ever a FAST PATH, not the source of truth: a phone's
 * QR-scanning app (Camera vs. a Control Center-style scanner, etc.) doesn't
 * always hand off to a browsing context that carries cookies forward, so
 * whenever the cookie doesn't resolve a guest, CustomerOrderController
 * shows the guest a name-based identify screen instead of silently minting
 * a new one — see {@see resumeGuest()} and {@see createNamedGuest()}, which
 * that screen's confirmation resolves onto. Sessions close when the table
 * is released (Space goes back to Available) or by the safety expiry, and
 * closed sessions accept no further orders.
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
     * the cookie when valid, freshly numbered (anonymous) otherwise. Used
     * by {@see \App\Http\Controllers\CustomerOrderController::store()},
     * which must never block a submission behind the identify screen, so
     * it degrades gracefully to an anonymous "Guest N" rather than erroring
     * when no cookie is present. Queues the cookie on the response either
     * way, refreshing its lifetime.
     */
    public static function resolveGuest(Request $request, SpaceSession $session): GuestSession
    {
        return self::tryResolveGuestFromCookie($request, $session) ?? self::createNamedGuest($session, null);
    }

    /**
     * Read-only cookie lookup — returns null instead of creating a guest
     * when the cookie is missing or stale, so the caller can show the
     * name-based identify screen instead of silently minting a new child.
     */
    public static function tryResolveGuestFromCookie(Request $request, SpaceSession $session): ?GuestSession
    {
        $token = $request->cookie(self::cookieName($session));

        $guest = $token
            ? $session->guestSessions()->where('public_token', $token)->where('status', 'active')->first()
            : null;

        return $guest ? self::bindGuestCookie($session, $guest) : null;
    }

    /**
     * Re-attaches a SPECIFIC, already-known guest to this browser — the
     * identify screen's "yes, that's me" confirmation resolves onto this,
     * so a guest whose cookie didn't survive a rescan (or who's revisiting
     * from "Order Again") still lands back on their own cart/order instead
     * of a new one, regardless of which scanner/browser context is used.
     */
    public static function resumeGuest(SpaceSession $session, GuestSession $guest): GuestSession
    {
        return self::bindGuestCookie($session, $guest);
    }

    /**
     * Mints a brand-new guest seat, numbered the same way as before
     * (max + 1 within this table session) but optionally carrying the name
     * the guest typed on the identify screen — falls back to the plain
     * "Guest N" label (via GuestSession::displayLabel()) when left blank.
     */
    public static function createNamedGuest(SpaceSession $session, ?string $name): GuestSession
    {
        $name = $name !== null ? trim($name) : null;
        $name = $name !== '' ? $name : null;

        $guest = DB::transaction(function () use ($session, $name) {
            $nextNumber = ((int) $session->guestSessions()->lockForUpdate()->max('guest_number')) + 1;

            return $session->guestSessions()->create(['guest_number' => $nextNumber, 'display_name' => $name]);
        });

        return self::bindGuestCookie($session, $guest);
    }

    protected static function bindGuestCookie(SpaceSession $session, GuestSession $guest): GuestSession
    {
        $guest->update(['last_active_at' => now()]);

        Cookie::queue(cookie(
            name: self::cookieName($session),
            value: $guest->public_token,
            minutes: self::SESSION_LIFETIME_HOURS * 60,
            secure: null,
            httpOnly: true,
            sameSite: 'lax',
        ));

        return $guest;
    }

    /**
     * Cookie names are scoped per TABLE (space), not per session — so a
     * phone that hops between tables still never bleeds a stale guest
     * identity across them (each table keeps its own cookie), but a phone
     * revisiting the SAME table across many sessions over time reuses (and
     * simply overwrites) the same cookie name instead of leaving a new,
     * never-cleaned-up one behind every time.
     *
     * Previously this embedded the session's own random public_token in
     * the cookie NAME. Since a new SpaceSession (new random token) is
     * created every time a table's session expires/reopens, that meant
     * every re-scan of the same physical QR code over the weeks/months
     * left behind a brand-new cookie that nothing ever removed — a phone
     * used to test/scan many tables' QR codes repeatedly (exactly what
     * happens during development) accumulates an ever-growing pile of
     * `gs_*` cookies, until the cumulative Cookie header gets large enough
     * to trip PHP-FPM/nginx's response header buffer ("upstream sent too
     * big header", seen in production 2026-09-14). Scoping by space id
     * instead means there is at most one guest cookie per table, ever.
     * Correctness is unaffected: tryResolveGuestFromCookie() already
     * checks the cookie's guest token against the CURRENT active session
     * only, so a leftover value from a since-closed session on the same
     * table still correctly falls through to the identify screen.
     */
    public static function cookieName(SpaceSession $session): string
    {
        return 'gs_space_'.$session->space_id;
    }
}
