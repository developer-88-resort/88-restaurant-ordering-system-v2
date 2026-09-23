{{--
    Turns on the shared-tablet idle sign-out (resources/js/lib/idle-timeout.js)
    for Staff/Admin only. Superadmin pages, guest pages and the customer QR
    menu never carry it, so the timer never runs there.
--}}
@auth
    @if (auth()->user()->usesPin())
        <meta
            name="idle-timeout"
            content="{{ config('auth.pin.idle_timeout_minutes') * 60 }}"
            data-session-key="{{ substr(hash('sha256', session()->getId()), 0, 16) }}"
            data-logout-url="{{ route('logout') }}"
            data-heartbeat-url="{{ route('heartbeat') }}"
            data-warning-text="{{ __('Signing out in :seconds s for inactivity — tap anywhere to stay signed in.') }}"
        >
    @endif
@endauth
