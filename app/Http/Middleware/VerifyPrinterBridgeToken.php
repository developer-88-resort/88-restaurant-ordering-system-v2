<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the printer-bridge API (routes/api.php) — these aren't hit by a
 * logged-in user, they're polled by the printer:bridge process on the
 * resort's local network, authenticated with a shared secret instead of a
 * user session.
 */
class VerifyPrinterBridgeToken
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('printing.bridge_token');
        $given = $request->bearerToken();

        if (! $configured || ! $given || ! hash_equals($configured, $given)) {
            abort(401);
        }

        return $next($request);
    }
}
