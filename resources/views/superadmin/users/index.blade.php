<x-app-layout>
    <x-slot name="header">
        <section
            class="rounded-2xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-7"
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
            <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4 sm:gap-5">
                    <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl border border-slate-200 bg-slate-50 text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-7 w-7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h2 class="text-xl font-semibold tracking-[-0.025em] text-slate-900 sm:text-2xl">
                                {{ __('Manage Users') }}
                            </h2>
                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                                {{ trans_choice(':count user|:count users', $users->count(), ['count' => $users->count()]) }}
                            </span>
                            @if (count($onlineUserIds) > 0)
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                    {{ trans_choice(':count online now|:count online now', count($onlineUserIds), ['count' => count($onlineUserIds)]) }}
                                </span>
                            @endif
                        </div>
                        <p class="mt-1.5 max-w-md text-sm leading-6 text-slate-500">
                            {{ __('Invite Superadmin, Admin, and Staff accounts to give them access to this portal.') }}
                        </p>
                    </div>
                </div>

                <a
                    href="{{ route('superadmin.users.create') }}"
                    class="group inline-flex min-h-11 w-fit items-center justify-center gap-2.5 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-sm transition-colors hover:bg-slate-50 hover:border-slate-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2"
                >
                    <span class="grid h-6 w-6 place-items-center text-slate-500">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 20H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v5M7 15c0-2.5 6-2.5 6 0M12 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0M19 14v6m-3-3h6" />
                        </svg>
                    </span>
                    {{ __('Add User') }}
                </a>
            </div>
        </section>
    </x-slot>

    @php
        $roleAccent = fn ($role) => match ($role->value) {
            'superadmin' => 'border-slate-200 bg-slate-100 text-slate-700',
            'admin' => 'border-blue-200 bg-blue-50 text-blue-700',
            default => 'border-slate-200 bg-slate-100 text-slate-700',
        };

        // Where each Staff/Admin stands on their sign-in PIN, so a Superadmin
        // can see at a glance who still has to be handed one and who is still
        // on the starting PIN. A Superadmin signs in with a password and has
        // no PIN of their own to report.
        $pinState = function ($user) {
            if (! $user->usesPin() || $user->isPendingActivation()) {
                return null;
            }

            if (! $user->hasPin()) {
                return ['label' => __('No PIN yet'), 'classes' => 'border-amber-200 bg-amber-50 text-amber-700', 'note' => __('Give them a starting PIN.')];
            }

            if ($user->pin_changed_at === null) {
                return ['label' => __('Starting PIN'), 'classes' => 'border-amber-200 bg-amber-50 text-amber-700', 'note' => __('They choose their own at their next sign-in.')];
            }

            return [
                'label' => __('PIN set'),
                'classes' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                'note' => __('Set :date', ['date' => $user->pin_changed_at->format('M d, Y')]),
            ];
        };
    @endphp

    @if ($users->isEmpty())
        <x-empty-state
            :title="__('No user accounts yet')"
            :description="__('Invite Superadmin, Admin, and Staff accounts to give them access to this portal.')"
            :actionLabel="__('Add User')"
            :actionHref="route('superadmin.users.create')"
        />
    @else
        {{-- Desktop table --}}
        <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:block">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Name') }}</th>
                            <th class="px-6 py-3.5 text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Email') }}</th>
                            <th class="px-6 py-3.5 text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Role') }}</th>
                            <th class="px-6 py-3.5 text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Sign-in PIN') }}</th>
                            <th class="px-6 py-3.5 text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Status') }}</th>
                            <th class="px-6 py-3.5 text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Online') }}</th>
                            <th class="px-6 py-3.5 text-right text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($users as $user)
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-6 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <x-avatar :user="$user" class="h-9 w-9 text-xs" />
                                        <span class="text-sm font-semibold text-slate-900">
                                            {{ $user->name }}
                                            @if ($user->id === auth()->id())
                                                <span class="text-xs font-medium text-slate-400">({{ __('You') }})</span>
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-3.5 text-sm text-slate-600">
                                    {{ $user->email ?? __('No email') }}
                                </td>
                                <td class="px-6 py-3.5">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] {{ $roleAccent($user->role) }}">
                                        {{ $user->role->label() }}
                                    </span>
                                </td>
                                <td class="px-6 py-3.5">
                                    @php $pin = $pinState($user); @endphp
                                    @if ($pin)
                                        <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] {{ $pin['classes'] }}">
                                            {{ $pin['label'] }}
                                        </span>
                                        <span class="mt-0.5 block text-[11px] text-slate-500">{{ $pin['note'] }}</span>
                                    @else
                                        <span class="text-sm text-slate-400">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3.5">
                                    @if ($user->isPendingActivation())
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] {{ $user->invitationStatus()->badgeClasses() }}">
                                            {{ $user->invitationStatus()->label() }}
                                        </span>
                                    @elseif ($user->is_active)
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            {{ __('Active') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-600">
                                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                            {{ __('Inactive') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-3.5">
                                    @if (in_array($user->id, $onlineUserIds))
                                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                            {{ __('Online') }}
                                        </span>
                                        <p class="mt-0.5 flex items-center gap-1 pl-3.5 text-[11px] text-slate-500">
                                            <x-device-icon :type="$deviceByUserId[$user->id]['type'] ?? 'desktop'" class="h-3.5 w-3.5 shrink-0" />
                                            {{ $deviceByUserId[$user->id]['label'] ?? __('Unknown device') }}
                                            @if (($extraSessionCountByUserId[$user->id] ?? 0) > 0)
                                                · {{ trans_choice('+:count more device|+:count more devices', $extraSessionCountByUserId[$user->id], ['count' => $extraSessionCountByUserId[$user->id]]) }}
                                            @endif
                                        </p>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-400">
                                            <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                                            {{ __('Offline') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-3.5">
                                    <div class="flex items-center justify-end gap-2">
                                        @if ($user->id === auth()->id())
                                            <span class="text-xs font-medium text-slate-400">{{ __('Edit') }}</span>
                                        @else
                                            @if ($user->isPendingActivation())
                                                <form action="{{ route('superadmin.users.resend-invitation', $user) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 010 12h-3" />
                                                        </svg>
                                                        {{ __('Resend') }}
                                                    </button>
                                                </form>
                                            @endif

                                            <a href="{{ route('superadmin.users.edit', $user) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM16.862 4.487 19.5 7.125" />
                                                </svg>
                                                {{ __('Edit') }}
                                            </a>

                                            @unless ($user->is_active)
                                                <form action="{{ route('superadmin.users.reactivate', $user) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-100">
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
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        <x-avatar :user="$user" class="h-11 w-11 shrink-0 text-sm" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">
                                {{ $user->name }}
                                @if ($user->id === auth()->id())
                                    <span class="text-xs font-medium text-slate-400">({{ __('You') }})</span>
                                @endif
                            </p>
                            <p class="truncate text-xs text-slate-500">
                                {{ $user->email ?? __('No email') }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-1.5">
                        <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] {{ $roleAccent($user->role) }}">
                            {{ $user->role->label() }}
                        </span>
                        @php $pin = $pinState($user); @endphp
                        @if ($pin)
                            <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] {{ $pin['classes'] }}" title="{{ $pin['note'] }}">
                                {{ $pin['label'] }}
                            </span>
                        @endif
                        @if ($user->isPendingActivation())
                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] {{ $user->invitationStatus()->badgeClasses() }}">
                                {{ $user->invitationStatus()->label() }}
                            </span>
                        @elseif ($user->is_active)
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-emerald-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                {{ __('Active') }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-600">
                                <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                {{ __('Inactive') }}
                            </span>
                        @endif
                        @if (in_array($user->id, $onlineUserIds))
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                {{ __('Online') }}
                                <span class="inline-flex items-center gap-1 font-normal text-slate-500">
                                    ·
                                    <x-device-icon :type="$deviceByUserId[$user->id]['type'] ?? 'desktop'" class="h-3.5 w-3.5 shrink-0" />
                                    {{ $deviceByUserId[$user->id]['label'] ?? __('Unknown device') }}
                                </span>
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-400">
                                <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                                {{ __('Offline') }}
                            </span>
                        @endif
                    </div>

                    @unless ($user->id === auth()->id())
                        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3.5">
                            @if ($user->isPendingActivation())
                                <form action="{{ route('superadmin.users.resend-invitation', $user) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 010 12h-3" />
                                        </svg>
                                        {{ __('Resend') }}
                                    </button>
                                </form>
                            @endif

                            <a href="{{ route('superadmin.users.edit', $user) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM16.862 4.487 19.5 7.125" />
                                </svg>
                                {{ __('Edit') }}
                            </a>

                            @unless ($user->is_active)
                                <form action="{{ route('superadmin.users.reactivate', $user) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-100">
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
