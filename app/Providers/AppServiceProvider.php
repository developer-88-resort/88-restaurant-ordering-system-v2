<?php

namespace App\Providers;

use App\Events\AuditLogCreated;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
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

        if (config('app.url')) {
            URL::forceRootUrl(config('app.url'));
        }

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
