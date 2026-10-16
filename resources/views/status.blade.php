{{-- /status (App\Http\Controllers\StatusController): the system's status
     page, like status.claude.com, open to anyone at any time. Plain Blade
     with no app scripts; it reloads itself every minute. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="60">
    {{-- Always a full page load: Turbo (from the React sign-in page) must not
         swap this page in, or going back to sign in leaves a blank page. --}}
    <meta name="turbo-visit-control" content="reload">
    <title>{{ __('System Status') }} - 88 Hot Spring Resort</title>
    @if (file_exists(public_path('images/logo.png')))
        <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    @endif
    {{-- Figtree, the font the rest of the system uses (layouts/app.blade.php). --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="relative min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    {{-- English / 한국어, as on the sign-in page (POST /locale stays open during maintenance). --}}
    <div class="absolute right-4 top-4 flex items-center gap-1 rounded-xl border border-slate-200 bg-white p-1 text-xs font-semibold shadow-sm">
        @foreach (\App\Support\AvailableLocales::labels() as $code => $label)
            <form method="POST" action="{{ route('locale.update') }}" data-turbo="false">
                @csrf
                <input type="hidden" name="locale" value="{{ $code }}">
                <button type="submit" class="rounded-lg px-2.5 py-1.5 {{ app()->getLocale() === $code ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50' }}">{{ $label }}</button>
            </form>
        @endforeach
    </div>
    <main class="mx-auto w-full max-w-3xl px-4 py-10 sm:py-14">
        <header class="mb-10 flex items-center gap-3">
            @if (file_exists(public_path('images/logo.png')))
                <img src="{{ asset('images/logo.png') }}" alt="88 Hot Spring Resort" class="h-11 w-11 object-contain">
            @endif
            <div>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">88 Hot Spring Resort</h1>
                <p class="text-sm text-slate-500">{{ __('System Status') }}</p>
            </div>
        </header>

        @if ($status['activeIncidents']->isEmpty())
            <div class="mb-10 flex items-center gap-3 rounded-2xl bg-emerald-500 px-5 py-4 text-white shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-6 w-6 shrink-0" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-base font-semibold">{{ __('All systems operational') }}</p>
            </div>
        @else
            <div class="mb-6"></div>
        @endif

        @include('partials.status-sections')

        <footer class="mt-12 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-6 text-xs text-slate-400">
            <span>{{ __('This page checks again every minute.') }}</span>
            <a href="{{ route('login') }}" data-turbo="false" class="font-semibold text-slate-600 underline-offset-4 hover:underline">{{ __('Back to sign in') }}</a>
        </footer>
    </main>

    {{-- Follow Maintenance Mode: reload as soon as it is switched on or off
         (every 5 seconds, and the moment the tab is shown again), so the
         incident box and the banner are never a minute behind. --}}
    <script>
        (function () {
            var url = @js(route('maintenance.check'));
            var wasOn = @js(\App\Models\Setting::maintenanceIsOn());
            var check = function () {
                fetch(url, { cache: 'no-store', headers: { Accept: 'application/json' } })
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (data) { if (data && data.on !== wasOn) window.location.reload(); })
                    .catch(function () { /* offline for a moment: try again next time */ });
            };
            setInterval(check, 5000);
            document.addEventListener('visibilitychange', function () { if (!document.hidden) check(); });
            window.addEventListener('pageshow', function (event) { if (event.persisted) check(); });
        })();
    </script>
</body>
</html>
