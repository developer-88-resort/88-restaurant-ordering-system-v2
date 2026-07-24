<?php

namespace App\Http\Middleware;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\AvailableLocales;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    ...$request->user()->toArray(),
                    'avatar_url' => $request->user()->avatarUrl(),
                    'initials' => $request->user()->initials(),
                ] : null,
            ],
            'flash' => [
                'status' => $request->session()->get('status'),
                'error' => $request->session()->get('error'),
            ],
            'locale' => app()->getLocale(),
            'availableLocales' => AvailableLocales::labels(),
            // Shared with every Inertia page (not just the Dashboard) since
            // the sidebar's Order Management/Kitchen badges live in the
            // layout, mirroring the same inline query in layouts/app.blade.php.
            'pendingOrdersCount' => Order::where('status', OrderStatus::Pending)->count(),
            // Mirrors the same file_exists() choice layouts/app.blade.php
            // makes for its top-bar logo, so the React chrome shows the same
            // logo/shape instead of always falling back to the circular mark.
            'logo' => file_exists(public_path('images/logo2024.png'))
                ? ['url' => asset('images/logo2024.png'), 'wide' => true]
                : ['url' => asset('images/logo.png'), 'wide' => false],
            // The JSON-keyed translation file for the current locale — 'en'
            // has none (the __() keys already ARE the English text, same
            // convention as the Blade side), so React's t() helper falls
            // back to the key itself exactly like Laravel's own __() does.
            'translations' => $this->currentTranslations(),
            // Only needed for the handful of plain (non-Inertia) native form
            // posts, like the language switcher — a real full page reload is
            // wanted there since it changes translations app-wide, not just
            // this page's props.
            'csrf_token' => csrf_token(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function currentTranslations(): array
    {
        $path = lang_path(app()->getLocale().'.json');

        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?? [];
    }
}
