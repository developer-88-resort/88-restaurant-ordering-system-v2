<x-app-layout>
    <x-slot name="header">
        <section
            class="relative isolate overflow-hidden rounded-[2rem] bg-[#241917] px-6 py-6 shadow-[0_28px_65px_-36px_rgba(36,25,23,0.85)] sm:px-8 sm:py-7"
            x-data
            x-init="
                // Instant refresh the moment someone signs in or out...
                Echo.private('user-presence').listen('.UserPresenceChanged', () => window.location.reload());
                // ...and a periodic fallback for the case nothing broadcasts:
                // a session simply going idle past the 5-minute online window.
                const timer = setInterval(() => window.location.reload(), 30000);
                turboCleanup(() => {
                    Echo.leave('user-presence');
                    clearInterval(timer);
                });
            "
        >
            <div
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="background-image: linear-gradient(rgba(255,255,255,0.7) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.7) 1px, transparent 1px); background-size: 28px 28px;"
            ></div>

            <div aria-hidden="true" class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-[#A84742]/40 blur-3xl"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 left-1/3 h-56 w-56 rounded-full bg-white/5 blur-3xl"></div>

            <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4 sm:gap-5">
                    <div class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-white/15 bg-white/10 text-white backdrop-blur-sm sm:h-16 sm:w-16">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-7 w-7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h2 class="text-xl font-bold tracking-[-0.025em] text-white sm:text-2xl">
                                {{ __('Manage Users') }}
                            </h2>
                            <span class="inline-flex items-center rounded-full border border-white/10 bg-white/10 px-2.5 py-1 text-[11px] font-bold text-white/80 backdrop-blur-sm">
                                {{ trans_choice(':count user|:count users', $users->count(), ['count' => $users->count()]) }}
                            </span>
                            @if (count($onlineUserIds) > 0)
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/10 px-2.5 py-1 text-[11px] font-bold text-white/80 backdrop-blur-sm">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                    {{ trans_choice(':count online now|:count online now', count($onlineUserIds), ['count' => count($onlineUserIds)]) }}
                                </span>
                            @endif
                        </div>
                        <p class="mt-1.5 max-w-md text-sm leading-6 text-white/55">
                            {{ __('Invite Superadmin, Admin, and Staff accounts to give them access to this portal.') }}
                        </p>
                    </div>
                </div>

                <a
                    href="{{ route('superadmin.users.create') }}"
                    class="group inline-flex w-fit items-center justify-center gap-2 rounded-2xl bg-white px-4 py-3 text-sm font-black text-[#7B2D2A] shadow-[0_16px_32px_-18px_rgba(0,0,0,0.75)] transition hover:-translate-y-0.5 hover:bg-[#FFF7F3] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/15"
                >
                    <span class="grid h-6 w-6 place-items-center rounded-lg bg-[#8A3330]/10">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </span>
                    {{ __('Invite User') }}
                </a>
            </div>
        </section>
    </x-slot>

    @php
        $roleAccent = fn ($role) => match ($role->value) {
            'superadmin' => 'border-[#E6CBC3] bg-[#F8EAE6] text-[#8A3330]',
            'admin' => 'border-blue-200 bg-blue-50 text-blue-700',
            default => 'border-slate-200 bg-slate-100 text-slate-700',
        };
    @endphp

    @if ($users->isEmpty())
        <x-empty-state
            :title="__('No user accounts yet')"
            :description="__('Invite Superadmin, Admin, and Staff accounts to give them access to this portal.')"
            :actionLabel="__('Invite User')"
            :actionHref="route('superadmin.users.create')"
        />
    @else
        {{-- Desktop table --}}
        <div class="hidden overflow-hidden rounded-[1.75rem] border border-[#E5DDD0] bg-white shadow-[0_22px_60px_-48px_rgba(55,35,30,0.7)] sm:block">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#EEE5DC]">
                    <thead class="bg-[#FAF6EE]">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{{ __('Name') }}</th>
                            <th class="px-6 py-3.5 text-left text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{{ __('Email') }}</th>
                            <th class="px-6 py-3.5 text-left text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{{ __('Role') }}</th>
                            <th class="px-6 py-3.5 text-left text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{{ __('Status') }}</th>
                            <th class="px-6 py-3.5 text-left text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{{ __('Online') }}</th>
                            <th class="px-6 py-3.5 text-right text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#EEE5DC]">
                        @foreach ($users as $user)
                            <tr class="transition hover:bg-[#FAF6EE]">
                                <td class="px-6 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <x-avatar :user="$user" class="h-9 w-9 text-xs" />
                                        <span class="text-sm font-bold text-[#251C19]">
                                            {{ $user->name }}
                                            @if ($user->id === auth()->id())
                                                <span class="text-xs font-medium text-[#B0A49E]">({{ __('You') }})</span>
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-3.5 text-sm text-[#6C5E57]">{{ $user->email }}</td>
                                <td class="px-6 py-3.5">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] {{ $roleAccent($user->role) }}">
                                        {{ $user->role->label() }}
                                    </span>
                                </td>
                                <td class="px-6 py-3.5">
                                    @if ($user->isPendingActivation())
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] {{ $user->invitationStatus()->badgeClasses() }}">
                                            {{ $user->invitationStatus()->label() }}
                                        </span>
                                    @elseif ($user->is_active)
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] text-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            {{ __('Active') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-600">
                                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                            {{ __('Inactive') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-3.5">
                                    @if (in_array($user->id, $onlineUserIds))
                                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700">
                                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                            {{ __('Online') }}
                                        </span>
                                        <p class="mt-0.5 flex items-center gap-1 pl-3.5 text-[11px] text-[#9A8B84]">
                                            <x-device-icon :type="$deviceByUserId[$user->id]['type'] ?? 'desktop'" class="h-3.5 w-3.5 shrink-0" />
                                            {{ $deviceByUserId[$user->id]['label'] ?? __('Unknown device') }}
                                            @if (($extraSessionCountByUserId[$user->id] ?? 0) > 0)
                                                · {{ trans_choice('+:count more device|+:count more devices', $extraSessionCountByUserId[$user->id], ['count' => $extraSessionCountByUserId[$user->id]]) }}
                                            @endif
                                        </p>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-[#B0A49E]">
                                            <span class="h-2 w-2 rounded-full bg-[#DED3C7]"></span>
                                            {{ __('Offline') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-3.5">
                                    <div class="flex items-center justify-end gap-2">
                                        @if ($user->id === auth()->id())
                                            <span class="text-xs font-medium text-[#D8CDC3]">{{ __('Edit') }}</span>
                                        @else
                                            @if ($user->isPendingActivation())
                                                <form action="{{ route('superadmin.users.resend-invitation', $user) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-[#E5D9CC] bg-[#FCF9F5] px-3 py-2 text-xs font-bold text-[#6E5E57] transition hover:border-[#CDAEA4] hover:bg-[#F8EEEA] hover:text-[#8A3330]">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 010 12h-3" />
                                                        </svg>
                                                        {{ __('Resend') }}
                                                    </button>
                                                </form>
                                            @endif

                                            <a href="{{ route('superadmin.users.edit', $user) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-[#E5D9CC] bg-[#FCF9F5] px-3 py-2 text-xs font-bold text-[#6E5E57] transition hover:border-[#CDAEA4] hover:bg-[#F8EEEA] hover:text-[#8A3330]">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM16.862 4.487 19.5 7.125" />
                                                </svg>
                                                {{ __('Edit') }}
                                            </a>

                                            @unless ($user->is_active)
                                                <form action="{{ route('superadmin.users.reactivate', $user) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-100">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                        </svg>
                                                        {{ __('Reactivate') }}
                                                    </button>
                                                </form>
                                            @endunless
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mobile cards --}}
        <div class="space-y-3 sm:hidden">
            @foreach ($users as $user)
                <div class="overflow-hidden rounded-2xl border border-[#E5DDD0] bg-white p-4 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)]">
                    <div class="flex items-center gap-3">
                        <x-avatar :user="$user" class="h-11 w-11 shrink-0 text-sm" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-[#251C19]">
                                {{ $user->name }}
                                @if ($user->id === auth()->id())
                                    <span class="text-xs font-medium text-[#B0A49E]">({{ __('You') }})</span>
                                @endif
                            </p>
                            <p class="truncate text-xs text-[#8B7D75]">{{ $user->email }}</p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-1.5">
                        <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] {{ $roleAccent($user->role) }}">
                            {{ $user->role->label() }}
                        </span>
                        @if ($user->isPendingActivation())
                            <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] {{ $user->invitationStatus()->badgeClasses() }}">
                                {{ $user->invitationStatus()->label() }}
                            </span>
                        @elseif ($user->is_active)
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] text-emerald-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                {{ __('Active') }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-600">
                                <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                {{ __('Inactive') }}
                            </span>
                        @endif
                        @if (in_array($user->id, $onlineUserIds))
                            <span class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                {{ __('Online') }}
                                <span class="inline-flex items-center gap-1 font-normal text-[#9A8B84]">
                                    ·
                                    <x-device-icon :type="$deviceByUserId[$user->id]['type'] ?? 'desktop'" class="h-3.5 w-3.5 shrink-0" />
                                    {{ $deviceByUserId[$user->id]['label'] ?? __('Unknown device') }}
                                </span>
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-[#B0A49E]">
                                <span class="h-2 w-2 rounded-full bg-[#DED3C7]"></span>
                                {{ __('Offline') }}
                            </span>
                        @endif
                    </div>

                    @unless ($user->id === auth()->id())
                        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-[#EEE6DC] pt-3.5">
                            @if ($user->isPendingActivation())
                                <form action="{{ route('superadmin.users.resend-invitation', $user) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-[#E5D9CC] bg-[#FCF9F5] px-3 py-2 text-xs font-bold text-[#6E5E57] transition hover:border-[#CDAEA4] hover:bg-[#F8EEEA] hover:text-[#8A3330]">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 010 12h-3" />
                                        </svg>
                                        {{ __('Resend') }}
                                    </button>
                                </form>
                            @endif

                            <a href="{{ route('superadmin.users.edit', $user) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-[#E5D9CC] bg-[#FCF9F5] px-3 py-2 text-xs font-bold text-[#6E5E57] transition hover:border-[#CDAEA4] hover:bg-[#F8EEEA] hover:text-[#8A3330]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM16.862 4.487 19.5 7.125" />
                                </svg>
                                {{ __('Edit') }}
                            </a>

                            @unless ($user->is_active)
                                <form action="{{ route('superadmin.users.reactivate', $user) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-100">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                        {{ __('Reactivate') }}
                                    </button>
                                </form>
                            @endunless
                        </div>
                    @endunless
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
