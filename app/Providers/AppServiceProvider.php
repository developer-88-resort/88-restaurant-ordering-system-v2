<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Events\AuditLogCreated;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Single source of truth for every Password::defaults() call in the
        // app (invitation accept, password reset, account settings) — the
        // frontend's PasswordRequirements checklist mirrors these same 4
        // rules, so if this ever changes, update that component too.
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());

        // Setting the day's per-kilo market rate is the control that keeps
        // staff from typing a price at the scale, so it sits with the
        // manager tier (superadmin + admin) — the same pair every other
        // "only a manager can do this" check in this app uses. Named as an
        // ability rather than an inline role check so the rule lives in one
        // place and can later move to a real permissions table without
        // touching the controller or the routes.
        Gate::define('weigh.set_daily_price', fn (User $user) => in_array(
            $user->role,
            [UserRole::Superadmin, UserRole::Admin],
            true,
        ));

        // Recording a weight is ordinary counter work, so every operational
        // role can do it — the control against a made-up price isn't who
        // holds the scale, it's that the ₱/kg comes from the day's market
        // price rather than from whoever is typing.
        Gate::define('weigh.record', fn (User $user) => in_array(
            $user->role,
            [UserRole::Superadmin, UserRole::Admin, UserRole::Staff],
            true,
        ));

        // Departing from the day's rate for one line is a manager decision,
        // and always carries a written reason.
        Gate::define('weigh.override_price', fn (User $user) => in_array(
            $user->role,
            [UserRole::Superadmin, UserRole::Admin],
            true,
        ));

        // Deliberately NOT forcing APP_URL as the root for every generated
        // URL: this app is reachable both via the LAN IP (day-to-day use)
        // and, temporarily, via a Cloudflare Tunnel HTTPS URL (for demos).
        // Forcing one fixed root broke whichever of the two *wasn't* the
        // current APP_URL value (cross-origin asset loads get blocked by
        // the browser). Leaving this unset makes Laravel derive the
        // scheme+host from the actual incoming request instead, which
        // works correctly for both — TrustProxies (below) is what lets it
        // detect HTTPS correctly when arriving through the tunnel.

        if (app()->environment('local')) {
            $this->clearStaleViteHotFile();
        }

        // Model changes (Area, MenuCategory, MenuItem, Order, Setting, Space,
        // SpaceCategory, User) are audit-logged via the LogsAuditActivity
        // trait on each model, not an observer — see app/Concerns/LogsAuditActivity.php.

        Event::listen(function (Login $event) {
            activity('audit')
                ->causedBy($event->user)
                ->performedOn($event->user)
                ->event('login')
                ->log("{$event->user->name} logged in.");
        });

        Event::listen(function (Logout $event) {
            if (! $event->user) {
                return;
            }

            activity('audit')
                ->causedBy($event->user)
                ->performedOn($event->user)
                ->event('logout')
                ->log("{$event->user->name} logged out.");
        });

        Event::listen(function (Failed $event) {
            activity('audit')
                ->causedBy($event->user)
                ->event('failed_login')
                ->log('Failed login attempt for: '.($event->credentials['email'] ?? 'unknown'));
        });

        // Keep the Audit Logs page's real-time auto-reload working now that
        // entries are written by spatie/laravel-activitylog instead of the
        // old AuditLog model (which used to broadcast this itself).
        Activity::created(function () {
            broadcast(new AuditLogCreated());
        });
    }

    /**
     * `public/hot` is Vite's marker that a dev server is running — Laravel's
     * @vite() directive points every asset at it when present. If the dev
     * server process dies without a clean shutdown (closed terminal window,
     * PC sleep, etc.) this file is left behind, silently pointing every page
     * at a dead URL — the whole site (and, since it's a LAN address the
     * browser resolves locally, especially any phone on the same WiFi)
     * renders unstyled. Self-heal it here: if the file exists but nothing
     * answers on that host/port, delete it so @vite() falls back to the
     * built assets in public/build instead.
     */
    protected function clearStaleViteHotFile(): void
    {
        $hotFile = public_path('hot');

        if (! file_exists($hotFile)) {
            return;
        }

        $url = trim(file_get_contents($hotFile));
        $host = trim(parse_url($url, PHP_URL_HOST) ?: 'localhost', '[]');
        $port = parse_url($url, PHP_URL_PORT) ?: 5173;

        $connection = @fsockopen($host, $port, $errno, $errstr, 0.15);

        if ($connection) {
            fclose($connection);

            return;
        }

        @unlink($hotFile);
    }
}
