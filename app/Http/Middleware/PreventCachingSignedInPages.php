<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After signing out, Back (button, phone key or swipe) must not bring a
 * signed-in screen back. Browsers keep recent pages in a back-forward cache
 * and replay them without asking the server; "no-store" keeps a signed-in
 * page out of it, so Back has to ask again and the auth middleware sends
 * the request to the login screen instead.
 *
 * The login screen gets the same treatment, so Back onto it after signing
 * in is asked again too and lands where a signed-in user belongs.
 *
 * resources/js/lib/session-guard.js covers a browser that caches anyway.
 */
class PreventCachingSignedInPages
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() || $request->routeIs('login')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
