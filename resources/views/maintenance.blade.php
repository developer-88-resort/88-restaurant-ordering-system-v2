{{-- Maintenance Mode page (App\Http\Middleware\ShowMaintenancePage). Plain
     Blade with no app scripts, so it never needs a signed-in session. It
     reloads itself every minute, so when a Superadmin switches maintenance
     off, every open screen comes back on its own. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- A fallback only: the script at the bottom checks every 5 seconds. --}}
    <meta http-equiv="refresh" content="60">
    <meta name="turbo-visit-control" content="reload">
    <meta name="robots" content="noindex">
    <title>{{ __('Website under maintenance') }} - 88 Hot Spring Resort</title>
    @if (file_exists(public_path('images/logo.png')))
        <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    @endif
    {{-- Figtree, the font the rest of the system uses (layouts/app.blade.php). --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
    {{-- The illustration's animation: the crane's load swings and lowers,
         the screen's lines fill in one by one, the orange tile blinks and
         the truck idles with its wheels turning. Still for anyone who asks
         their device for reduced motion. --}}
    <style>
        .mt-load { transform-box: view-box; transform-origin: 190px 24px; animation: mt-swing 3.6s ease-in-out infinite; }
        .mt-line { transform-box: fill-box; transform-origin: left center; animation: mt-fill 3.2s ease-in-out infinite; }
        .mt-line:nth-of-type(2) { animation-delay: 0.25s; }
        .mt-line:nth-of-type(3) { animation-delay: 0.5s; }
        .mt-line:nth-of-type(4) { animation-delay: 0.75s; }
        .mt-line:nth-of-type(5) { animation-delay: 1s; }
        .mt-tile { animation: mt-blink 1.6s ease-in-out infinite; }
        .mt-truck { animation: mt-idle 0.9s ease-in-out infinite; }
        .mt-wheel { transform-box: fill-box; transform-origin: center; animation: mt-spin 2.4s linear infinite; }
        .mt-boom { animation: mt-dash 1.4s linear infinite; }
        @keyframes mt-swing { 0%, 100% { transform: rotate(-2.5deg) translateY(0); } 50% { transform: rotate(2.5deg) translateY(4px); } }
        @keyframes mt-fill { 0% { transform: scaleX(0.15); opacity: 0.5; } 45%, 80% { transform: scaleX(1); opacity: 1; } 100% { transform: scaleX(0.15); opacity: 0.5; } }
        @keyframes mt-blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
        @keyframes mt-idle { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-1.5px); } }
        @keyframes mt-spin { to { transform: rotate(360deg); } }
        @keyframes mt-dash { to { stroke-dashoffset: -28; } }
        @media (prefers-reduced-motion: reduce) {
            .mt-load, .mt-line, .mt-tile, .mt-truck, .mt-wheel, .mt-boom { animation: none; }
        }
    </style>
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
    <main class="flex min-h-screen flex-col items-center justify-center px-6 py-12 text-center">
        {{-- The animation (LottieFiles "404 page not found", Lottie Simple License;
             public/animations/LICENSE.txt), played by lottie-web from our own
             server. The crane drawing below is only the fallback: it stays if
             the animation can't load, and is hidden once it plays. --}}
        {{-- The lamp and "404" fill only the middle of the animation's 1600x1300
             canvas, so it is drawn larger than this frame and the empty edges
             are cut off: the frame shows just the drawing, big. --}}
        <div id="mt-lottie-frame" class="relative hidden w-full max-w-2xl overflow-hidden" style="aspect-ratio: 600 / 380" aria-hidden="true">
            <div id="mt-lottie" class="absolute" style="width: 180%; aspect-ratio: 1600 / 1300; left: -37.75%; top: -43.7%"></div>
        </div>
        <div id="mt-fallback" class="w-full max-w-md">
        {{-- A browser window on scaffolding, lifted into place by a crane. --}}
        <svg viewBox="0 0 420 260" class="w-full max-w-md" role="img" aria-label="{{ __('Website under maintenance') }}">
            {{-- ground --}}
            <rect x="20" y="236" width="380" height="5" rx="2.5" fill="#CBD5E1" />

            {{-- scaffolding --}}
            <g stroke="#CBD5E1" stroke-width="3" fill="none">
                <path d="M50 150v86M110 150v86M170 150v86M230 150v86" />
                <path d="M50 150l60 43M110 150l-60 43M110 150l60 43M170 150l-60 43M170 150l60 43M230 150l-60 43" />
                <path d="M50 193l60 43M110 193l-60 43M110 193l60 43M170 193l-60 43M170 193l60 43M230 193l-60 43" />
                <path d="M50 193h180" />
            </g>
            <rect x="38" y="144" width="204" height="7" rx="3" fill="#94A3B8" />

            {{-- browser window --}}
            <rect x="62" y="56" width="156" height="88" rx="8" fill="#FFFFFF" stroke="#94A3B8" stroke-width="3" />
            <path d="M62 72h156" stroke="#94A3B8" stroke-width="3" />
            <circle cx="73" cy="64" r="2.5" fill="#E11D48" /><circle cx="81" cy="64" r="2.5" fill="#F59E0B" /><circle cx="89" cy="64" r="2.5" fill="#10B981" />
            <g>
                <rect class="mt-line" x="74" y="82" width="64" height="10" rx="2" fill="#8A3330" />
                <rect class="mt-line" x="74" y="98" width="64" height="4" rx="2" fill="#CBD5E1" />
                <rect class="mt-line" x="74" y="107" width="56" height="4" rx="2" fill="#CBD5E1" />
                <rect class="mt-line" x="74" y="116" width="60" height="4" rx="2" fill="#CBD5E1" />
                <rect class="mt-line" x="74" y="125" width="44" height="4" rx="2" fill="#CBD5E1" />
            </g>
            <g fill="#E2E8F0">
                <rect x="150" y="82" width="16" height="12" rx="2" /><rect x="172" y="82" width="16" height="12" rx="2" /><rect x="194" y="82" width="16" height="12" rx="2" />
                <rect x="150" y="100" width="16" height="12" rx="2" /><rect x="172" y="100" width="16" height="12" rx="2" /><rect x="194" y="100" width="16" height="12" rx="2" />
                <rect x="150" y="118" width="16" height="12" rx="2" /><rect x="172" y="118" width="16" height="12" rx="2" />
            </g>
            <rect class="mt-tile" x="194" y="118" width="16" height="12" rx="2" fill="#F59E0B" />

            {{-- crane --}}
            <path d="M330 196L190 24" stroke="#94A3B8" stroke-width="9" stroke-linecap="round" />
            <path class="mt-boom" d="M330 196L190 24" stroke="#CBD5E1" stroke-width="3" stroke-dasharray="6 8" stroke-linecap="round" />
            <g class="mt-load">
                <path d="M190 24v14" stroke="#64748B" stroke-width="2" />
                <circle cx="190" cy="42" r="4" fill="#64748B" />
                <path d="M190 46v6" stroke="#64748B" stroke-width="2" />
                <rect x="160" y="50" width="62" height="5" rx="2" fill="#8A3330" />
            </g>

            {{-- truck --}}
            <g class="mt-truck">
                <rect x="262" y="190" width="132" height="34" rx="6" fill="#E2E8F0" />
                <path d="M262 206h132" stroke="#CBD5E1" stroke-width="2" />
                <path d="M350 190v-24a6 6 0 016-6h22a6 6 0 016 6v24z" fill="#F1F5F9" stroke="#CBD5E1" stroke-width="2" />
                <rect x="358" y="168" width="18" height="12" rx="2" fill="#F59E0B" />
                <rect x="300" y="180" width="40" height="12" rx="3" fill="#94A3B8" />
            </g>
            {{-- wheels, with spokes so the turning shows --}}
            <g class="mt-wheel">
                <circle cx="290" cy="226" r="12" fill="#475569" /><circle cx="290" cy="226" r="5" fill="#CBD5E1" />
                <path d="M290 216v4M290 232v4M280 226h4M296 226h4" stroke="#94A3B8" stroke-width="2" stroke-linecap="round" />
            </g>
            <g class="mt-wheel">
                <circle cx="366" cy="226" r="12" fill="#475569" /><circle cx="366" cy="226" r="5" fill="#CBD5E1" />
                <path d="M366 216v4M366 232v4M356 226h4M372 226h4" stroke="#94A3B8" stroke-width="2" stroke-linecap="round" />
            </g>
        </svg>
        </div>

        @if (file_exists(public_path('images/logo.png')))
            <img src="{{ asset('images/logo.png') }}" alt="88 Hot Spring Resort" class="mt-8 h-12 w-auto">
        @endif

        <h1 class="mt-5 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ __('Website under maintenance') }}</h1>
        <p class="mt-3 max-w-lg text-base leading-7 text-slate-600">
            {{ $message ? __($message) : __('This website is currently undergoing scheduled maintenance. We should be back shortly.') }}
        </p>
        <p class="mt-6 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-500">
            <span class="h-2 w-2 motion-safe:animate-pulse rounded-full bg-amber-500"></span>
            {{ __('This page comes back on its own when we are done.') }}
        </p>

        {{-- The same status as /status, plus a link to it. --}}
        @isset($status)
            <div class="mt-12 w-full max-w-3xl">
                @include('partials.status-sections')
            </div>
            <a href="{{ route('status') }}" data-turbo="false" class="mt-8 text-sm font-semibold text-slate-700 underline-offset-4 hover:underline">{{ __('View system status') }} &rarr;</a>
        @endisset

        @if ($showSignInLink)
            <a href="{{ route('login', ['signin' => 1]) }}" data-turbo="false" class="mt-10 text-xs font-medium text-slate-400 underline-offset-4 hover:text-slate-600 hover:underline">
                {{ __('Superadmin sign-in') }}
            </a>
        @endif
    </main>

    <script src="{{ asset('vendor/lottie/lottie_light.min.js') }}"></script>
    <script>
        (function () {
            if (!window.lottie) return;
            var box = document.getElementById('mt-lottie');
            var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var anim = window.lottie.loadAnimation({
                container: box,
                renderer: 'svg',
                loop: !still,
                autoplay: !still,
                path: @js(asset('animations/maintenance.json')),
            });
            anim.addEventListener('DOMLoaded', function () {
                if (still) anim.goToAndStop(0, true);
                document.getElementById('mt-lottie-frame').classList.remove('hidden');
                document.getElementById('mt-fallback').classList.add('hidden');
            });
        })();
    </script>

    {{-- Back on its own: every 5 seconds ask whether Maintenance Mode is still
         on, and reload (onto the page they were on) as soon as it is off. Also
         asks the moment the tab is shown again, comes back online, or is
         restored from the browser's back/forward cache: a tab in the
         background has its timers slowed down by the browser. --}}
    <script>
        (function () {
            var url = @js(route('maintenance.check'));
            var check = function () {
                fetch(url, { cache: 'no-store', headers: { Accept: 'application/json' } })
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (data) { if (data && data.on === false) window.location.reload(); })
                    .catch(function () { /* offline for a moment: try again next time */ });
            };
            setInterval(check, 5000);
            document.addEventListener('visibilitychange', function () { if (!document.hidden) check(); });
            window.addEventListener('focus', check);
            window.addEventListener('online', check);
            window.addEventListener('pageshow', function (event) { if (event.persisted) check(); });
        })();
    </script>
</body>
</html>
