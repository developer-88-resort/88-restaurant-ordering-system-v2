<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @if (file_exists(public_path('images/logo.png')))
            <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
        @endif

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        @php
            $pendingOrdersCount = \App\Models\Order::where('status', \App\Enums\OrderStatus::Pending)->count();
        @endphp
        <div
            x-data="{
                sidebarOpen: false,
                sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === '1',
                pendingOrdersCount: {{ $pendingOrdersCount }},
                staffAlerts: [],
                pushStaffAlert(message) {
                    const id = Date.now() + Math.random();
                    this.staffAlerts.push({ id, message });
                    setTimeout(() => { this.staffAlerts = this.staffAlerts.filter(a => a.id !== id); }, 8000);
                },
            }"
            x-init="
                $watch('sidebarCollapsed', value => localStorage.setItem('sidebarCollapsed', value ? '1' : '0'));
                Echo.private('kitchen').listen('.KitchenUpdated', (e) => { pendingOrdersCount = e.pending_orders_count; });
                Echo.private('staff-alerts').listen('.StaffAssistanceRequested', (e) => pushStaffAlert(e.message));
                turboCleanup(() => { Echo.leave('kitchen'); Echo.leave('staff-alerts'); });
            "
            class="min-h-screen bg-[#F7F0E3]"
        >

            {{-- Top bar --}}
            <header class="bg-white border-b border-[#E5DDD0] sticky top-0 z-30">
                <div class="px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                        <button @click="sidebarOpen = true" type="button" class="lg:hidden -ml-2 p-2 rounded-md text-gray-500 hover:bg-gray-100" aria-label="{{ __('Open menu') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-6 w-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                            </svg>
                        </button>

                        {{-- data-turbo="false": homeRouteName() is the Inertia/React Dashboard — see the note on the Overview sidebar link below. --}}
                        <a href="{{ route(Auth::user()->homeRouteName()) }}" class="shrink-0" data-turbo="false">
                            @if (file_exists(public_path('images/logo2024.png')))
                                <img src="{{ asset('images/logo2024.png') }}" alt="88 Hot Spring Resort" class="h-9 w-auto object-contain">
                            @else
                                <img src="{{ asset('images/logo.png') }}" alt="88 Hot Spring Resort" class="h-10 w-10 rounded-full object-cover">
                            @endif
                        </a>
                        <span class="hidden sm:inline text-[#D9CCBA]">|</span>
                        <span class="hidden sm:inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wide bg-[#F3E1DC] text-[#8A3330]">
                            {{ Auth::user()->role->label() }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-4 shrink-0">
                        {{-- data-turbo="false": profile.edit is the Inertia/React Account Settings page — see the note on the Overview sidebar link above. --}}
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-800 {{ request()->routeIs('profile.edit') ? 'font-semibold text-[#8A3330]' : '' }}" data-turbo="false">
                            <x-avatar :user="Auth::user()" class="h-9 w-9 text-xs" />
                            <span class="hidden sm:inline">
                                {{ __('Signed in as') }} <span class="font-semibold text-gray-800">{{ Auth::user()->name }}</span>
                            </span>
                        </a>
                        <x-language-switcher />
                        <form method="POST" action="{{ route('logout') }}" data-turbo="false">
                            @csrf
                            <button type="submit" class="px-3 sm:px-4 py-1.5 rounded-lg border border-[#D9CCBA] text-sm font-medium text-gray-700 hover:bg-gray-50">
                                {{ __('Sign out') }}
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <x-toast />

            {{-- Live "Call a Staff" alerts — pushed in real time, separate
                 from the session-flash <x-toast/> above since these can
                 arrive on any staff page at any moment. --}}
            <div class="fixed bottom-4 inset-x-4 sm:inset-x-auto sm:right-4 z-[70] flex flex-col gap-3 sm:w-96">
                <template x-for="alert in staffAlerts" :key="alert.id">
                    <div
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-3 scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 translate-y-1"
                        class="flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 pl-4 pr-3 py-3.5 shadow-xl"
                    >
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 text-amber-700">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                        </div>
                        <p class="flex-1 pt-1 text-sm font-medium text-amber-900" x-text="alert.message"></p>
                        <button type="button" @click="staffAlerts = staffAlerts.filter(a => a.id !== alert.id)"
                                class="shrink-0 rounded-md p-1 text-amber-400 hover:bg-amber-100 hover:text-amber-700">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </template>
            </div>

            <div class="flex">

                {{-- Mobile backdrop --}}
                <div x-show="sidebarOpen" x-cloak x-transition.opacity
                     @click="sidebarOpen = false"
                     class="fixed inset-0 bg-black/40 z-40 lg:hidden"></div>

                {{-- Sidebar --}}
                <aside
                    x-cloak
                    :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full', sidebarCollapsed ? 'lg:w-20' : 'lg:w-60']"
                    class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-[#E5DDD0] px-4 py-5 overflow-y-auto overflow-x-hidden transform transition-all duration-200 ease-in-out lg:translate-x-0 lg:z-auto lg:shrink-0 lg:sticky lg:top-16 lg:h-[calc(100vh-4rem)]"
                >
                    {{-- Mobile header --}}
                    <div class="flex items-center justify-between mb-3 lg:hidden">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 px-2">{{ __('Menu') }}</p>
                        <button @click="sidebarOpen = false" type="button" class="p-2 rounded-md text-gray-500 hover:bg-gray-100" aria-label="{{ __('Close menu') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Desktop header: label + collapse toggle --}}
                    <div class="hidden lg:flex items-center mb-4 px-2" :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : 'justify-between'">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400" :class="sidebarCollapsed ? 'lg:hidden' : ''">{{ __('Menu') }}</p>
                        <button
                            @click="sidebarCollapsed = !sidebarCollapsed"
                            type="button"
                            class="p-1.5 rounded-md text-gray-400 hover:bg-[#F3E1DC]/70 hover:text-[#8A3330] transition-colors duration-150"
                            :aria-label="sidebarCollapsed ? '{{ __('Expand menu') }}' : '{{ __('Collapse menu') }}'"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4 transition-transform duration-200" :class="sidebarCollapsed ? 'rotate-180' : ''">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" />
                            </svg>
                        </button>
                    </div>

                    @php
                        $isSuperadmin = Auth::user()->role === \App\Enums\UserRole::Superadmin;
                        $canSeeReports = in_array(Auth::user()->role, [\App\Enums\UserRole::Superadmin, \App\Enums\UserRole::Admin], true);
                        $isOperational = in_array(Auth::user()->role, [\App\Enums\UserRole::Superadmin, \App\Enums\UserRole::Admin, \App\Enums\UserRole::Staff], true);
                    @endphp

                    <nav class="space-y-5" @click="sidebarOpen = false">
                        @if ($isOperational)
                            <x-sidebar-group :label="__('Dashboard')">
                                {{--
                                    data-turbo="false": the Dashboard is now an Inertia/React
                                    page, a completely different rendering stack from the rest
                                    of this still-Blade+Turbo layout. Without this, Turbo Drive
                                    intercepts the click and tries to XHR-fetch + morph the new
                                    page into the current DOM, which breaks React's mount
                                    (white screen) since Inertia's root view has a different
                                    head/body structure and script bundle (app.jsx, not app.js).
                                    Forcing a real full-page navigation here always works.
                                --}}
                                <x-sidebar-link :href="route('superadmin.dashboard')" :active="request()->routeIs('superadmin.dashboard')" data-turbo="false">
                                    <x-slot:icon>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                                        </svg>
                                    </x-slot:icon>
                                    {{ __('Overview') }}
                                </x-sidebar-link>
                            </x-sidebar-group>
                        @endif

                        @if ($isOperational)
                            <x-sidebar-group :label="__('Operations')">
                                <x-sidebar-link :href="route('orders.index')" :active="request()->routeIs('orders.*')" :badge="$pendingOrdersCount">
                                    <x-slot:icon>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08M18.75 18.75V9.375c0-.621-.504-1.125-1.125-1.125H8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                                        </svg>
                                    </x-slot:icon>
                                    {{ __('Order Management') }}
                                </x-sidebar-link>

                                <x-sidebar-link :href="route('kitchen.index')" :active="request()->routeIs('kitchen.*')" :badge="$pendingOrdersCount">
                                    <x-slot:icon>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.601a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 00.495-7.468 5.99 5.99 0 00-1.925 3.547 5.975 5.975 0 01-2.133-1.001A3.75 3.75 0 0012 18z" />
                                        </svg>
                                    </x-slot:icon>
                                    {{ __('Kitchen') }}
                                </x-sidebar-link>

                                {{-- data-turbo="false": Spaces is now an Inertia/React page — see the note on the Overview sidebar link above. --}}
                                <x-sidebar-link :href="route('spaces.index')" :active="request()->routeIs('spaces.*') || request()->routeIs('areas.*') || request()->routeIs('space-categories.*')" data-turbo="false">
                                    <x-slot:icon>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25a2.25 2.25 0 01-2.25-2.25v-2.25z" />
                                        </svg>
                                    </x-slot:icon>
                                    {{ __('Spaces') }}
                                </x-sidebar-link>
                            </x-sidebar-group>
                        @endif

                        @if ($isOperational)
                            <x-sidebar-group :label="__('Management')">
                                {{-- data-turbo="false": Menu Management is now an Inertia/React page — see the note on the Overview sidebar link above. --}}
                                <x-sidebar-link :href="route('menu-items.index')" :active="request()->routeIs('menu-items.*') || request()->routeIs('menu-categories.*')" data-turbo="false">
                                    <x-slot:icon>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                                        </svg>
                                    </x-slot:icon>
                                    {{ __('Menu Management') }}
                                </x-sidebar-link>

                                @if ($canSeeReports)
                                    <x-sidebar-link :href="route('superadmin.reports.index')" :active="request()->routeIs('superadmin.reports.*')">
                                        <x-slot:icon>
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                                            </svg>
                                        </x-slot:icon>
                                        {{ __('Reports') }}
                                    </x-sidebar-link>
                                @endif
                            </x-sidebar-group>
                        @endif

                        @if ($isSuperadmin)
                            <x-sidebar-group :label="__('Administration')">
                                <x-sidebar-link :href="route('superadmin.users.index')" :active="request()->routeIs('superadmin.users.*')">
                                    <x-slot:icon>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                        </svg>
                                    </x-slot:icon>
                                    {{ __('User Management') }}
                                </x-sidebar-link>

                                <x-sidebar-link :href="route('superadmin.audit-logs.index')" :active="request()->routeIs('superadmin.audit-logs.*')">
                                    <x-slot:icon>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                        </svg>
                                    </x-slot:icon>
                                    {{ __('Audit Logs') }}
                                </x-sidebar-link>
                            </x-sidebar-group>

                            <x-sidebar-group :label="__('System')">
                                <x-sidebar-link :href="route('superadmin.settings.edit')" :active="request()->routeIs('superadmin.settings.*')">
                                    <x-slot:icon>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </x-slot:icon>
                                    {{ __('Settings') }}
                                </x-sidebar-link>
                            </x-sidebar-group>
                        @endif

                        @if (! $isOperational)
                            <p class="px-3 py-2 text-sm text-gray-400" :class="sidebarCollapsed ? 'lg:hidden' : ''">{{ __('No areas assigned to your account yet.') }}</p>
                        @endif
                    </nav>
                </aside>

                {{-- Page Content --}}
                <main class="flex-1 min-w-0 p-4 sm:p-6">
                    @isset($header)
                        <div class="mb-6">
                            {{ $header }}
                        </div>
                    @endisset

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
