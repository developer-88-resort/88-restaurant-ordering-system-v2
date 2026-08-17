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
            $unreadChatCount = Auth::check() ? \App\Models\Message::unreadCountForUser(Auth::id()) : 0;
        @endphp
        <div
            x-data="{
                sidebarOpen: false,
                sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === '1',
                pendingOrdersCount: {{ $pendingOrdersCount }},
                unreadChatCount: {{ $unreadChatCount }},
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
                Echo.private('chat-inbox.{{ Auth::id() }}').listen('.ChatInboxUpdated', (e) => { unreadChatCount = e.unread_count; });
                turboCleanup(() => { Echo.leave('kitchen'); Echo.leave('staff-alerts'); Echo.leave('chat-inbox.{{ Auth::id() }}'); });
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
                        // THE sidebar — resolved once from config/navigation.php via
                        // App\Support\Navigation. The Inertia/React layout resolves
                        // the exact same call (see HandleInertiaRequests), so the two
                        // render stacks can never show a different menu again.
                        $navigation = \App\Support\Navigation::forUser(Auth::user());
                    @endphp

                    <nav class="space-y-5" @click="sidebarOpen = false">
                        @forelse ($navigation as $group)
                            <x-sidebar-group :label="$group['label']">
                                @foreach ($group['items'] as $item)
                                    {{--
                                        data-turbo="false" on an Inertia-rendered destination:
                                        Turbo Drive would otherwise intercept the click and try
                                        to XHR-fetch + morph the new page into this still-Blade
                                        DOM, which breaks React's mount (white screen) since
                                        Inertia's root view has a different head/body structure
                                        and script bundle (app.jsx, not app.js). Forcing a real
                                        full-page navigation here always works.
                                    --}}
                                    @if (! empty($item['children']))
                                        @php
                                            $hasActiveChild = collect($item['children'])->contains('active', true);
                                        @endphp
                                        {{-- A dropdown, not a permanently-open sub-list: with
                                             only one item it looked cluttered pinned open all
                                             the time. Starts open only when the current page IS
                                             this item or one of its children, so navigating here
                                             never hides where you already are.

                                             Children get no icon of their own (repeating the
                                             parent's icon read as redundant) and no left border
                                             (a drawn rule looked dated against the rest of the
                                             flat list) — just indentation and weight. --}}
                                        <div x-data="{ open: {{ Js::from($item['active'] || $hasActiveChild) }} }">
                                            <div
                                                class="flex items-center rounded-lg text-sm font-medium transition-colors duration-150 {{ ($item['active'] && ! $hasActiveChild) ? 'bg-[#8A3330] text-white shadow-sm shadow-[#8A3330]/20' : 'text-gray-600' }}"
                                                :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                                            >
                                                <a
                                                    href="{{ $item['href'] }}"
                                                    title="{{ $item['label'] }}"
                                                    data-nav-key="{{ $item['key'] }}"
                                                    data-turbo="{{ $item['inertia'] ? 'false' : 'true' }}"
                                                    class="flex-1 flex items-center gap-3 px-3 py-2.5 min-w-0 {{ ($item['active'] && ! $hasActiveChild) ? '' : 'hover:text-[#8A3330]' }}"
                                                >
                                                    <span class="shrink-0 h-5 w-5 [&>svg]:h-5 [&>svg]:w-5 {{ ($item['active'] && ! $hasActiveChild) ? 'text-white' : 'text-gray-400' }}">
                                                        <x-sidebar-icon :name="$item['icon']" />
                                                    </span>
                                                    <span class="truncate flex-1" :class="sidebarCollapsed ? 'lg:hidden' : ''">{{ $item['label'] }}</span>
                                                </a>
                                                <button
                                                    type="button"
                                                    @click.stop.prevent="open = !open"
                                                    :aria-expanded="open"
                                                    aria-label="{{ __('Toggle :item', ['item' => $item['label']]) }}"
                                                    class="shrink-0 grid place-items-center h-8 w-8 mr-1.5 rounded-md {{ ($item['active'] && ! $hasActiveChild) ? 'text-white/70 hover:text-white' : 'text-gray-400 hover:text-[#8A3330] hover:bg-[#F3E1DC]/70' }}"
                                                    :class="sidebarCollapsed ? 'lg:hidden' : ''"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5 transition-transform duration-150" :class="open ? 'rotate-180' : ''">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                    </svg>
                                                </button>
                                            </div>

                                            <div
                                                x-show="open"
                                                x-cloak
                                                x-transition
                                                class="mt-0.5 space-y-0.5"
                                                :class="sidebarCollapsed ? 'lg:hidden' : ''"
                                            >
                                                @foreach ($item['children'] as $child)
                                                    <a
                                                        href="{{ $child['href'] }}"
                                                        title="{{ $child['label'] }}"
                                                        data-nav-key="{{ $child['key'] }}"
                                                        data-turbo="{{ $child['inertia'] ? 'false' : 'true' }}"
                                                        class="block truncate rounded-lg py-2 pl-11 pr-3 text-sm transition-colors duration-150 {{ $child['active'] ? 'font-semibold text-[#8A3330]' : 'text-gray-500 hover:text-[#8A3330]' }}"
                                                    >
                                                        {{ $child['label'] }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        <x-sidebar-link
                                            :href="$item['href']"
                                            :active="$item['active']"
                                            :badge="$item['badge'] === 'unread_chat' ? $unreadChatCount : ($item['badge'] === 'pending_orders' ? $pendingOrdersCount : null)"
                                            :badge-key="$item['badge'] ?? 'pending_orders'"
                                            :data-nav-key="$item['key']"
                                            :data-turbo="$item['inertia'] ? 'false' : 'true'"
                                            :data-turbo-prefetch="$item['inertia'] ? 'false' : 'true'"
                                        >
                                            <x-slot:icon><x-sidebar-icon :name="$item['icon']" /></x-slot:icon>
                                            {{ $item['label'] }}
                                        </x-sidebar-link>
                                    @endif
                                @endforeach
                            </x-sidebar-group>
                        @empty
                            <p class="px-3 py-2 text-sm text-gray-400" :class="sidebarCollapsed ? 'lg:hidden' : ''">{{ __('No areas assigned to your account yet.') }}</p>
                        @endforelse
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
