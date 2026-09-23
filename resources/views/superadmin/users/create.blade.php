<x-app-layout>
    <x-slot name="header">
        <section class="relative isolate overflow-hidden rounded-[2rem] bg-[#241917] px-6 py-6 shadow-[0_28px_65px_-36px_rgba(36,25,23,0.85)] sm:px-8 sm:py-7">
            <div
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="background-image: linear-gradient(rgba(255,255,255,0.7) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.7) 1px, transparent 1px); background-size: 28px 28px;"
            ></div>

            <div aria-hidden="true" class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-[#A84742]/40 blur-3xl"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 left-1/3 h-56 w-56 rounded-full bg-white/5 blur-3xl"></div>

            <div class="relative flex items-center gap-4 sm:gap-5">
                <div class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-white/15 bg-white/10 text-white backdrop-blur-sm sm:h-16 sm:w-16">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-7 w-7" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                    </svg>
                </div>

                <div>
                    <h2 class="text-xl font-bold tracking-[-0.025em] text-white sm:text-2xl">
                        {{ __('Add User') }}
                    </h2>
                    <p class="mt-1.5 max-w-md text-sm leading-6 text-white/55">
                        {{ __('Staff and Admin sign in with their name and a PIN. A Superadmin gets an email invitation to set a password.') }}
                    </p>
                </div>
            </div>
        </section>
    </x-slot>

    <div x-data="{ role: {{ Js::from(old('role', 'staff')) }} }" class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        {{-- Account form --}}
        <div class="rounded-2xl border border-[#E5DDD0] bg-white p-6 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)] sm:p-8">
            <form method="POST" action="{{ route('superadmin.users.store') }}" data-draft-key="superadmin-user-create">
                @csrf

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name')" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="role" :value="__('Role')" />
                        <span class="relative mt-1 block">
                            <select id="role" name="role" x-model="role" class="h-[2.6rem] w-full appearance-none bg-none rounded-lg border-gray-300 py-2 pl-3 pr-9 shadow-sm focus:border-[#8A3330] focus:ring-[#8A3330]" required>
                                <option value="superadmin" @selected(old('role') === 'superadmin')>{{ __('Superadmin') }}</option>
                                <option value="admin" @selected(old('role') === 'admin')>{{ __('Admin') }}</option>
                                <option value="staff" @selected(old('role', 'staff') === 'staff')>{{ __('Staff') }}</option>
                            </select>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#9B8C84]" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6.75 9 5.25 5.25L17.25 9" />
                            </svg>
                        </span>
                        <x-input-error :messages="$errors->get('role')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-5">
                    <label for="email" class="block font-medium text-sm text-gray-700">
                        {{ __('Email') }}
                        <span x-show="role !== 'superadmin'" class="font-normal text-gray-400">({{ __('optional') }})</span>
                    </label>
                    <x-text-input id="email" name="email" type="email" class="block mt-1 w-full sm:max-w-md" :value="old('email')" x-bind:required="role === 'superadmin'" />
                    <p x-show="role === 'superadmin'" class="mt-1.5 text-xs text-[#8B7D75]">{{ __('The invitation goes to this address.') }}</p>
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                {{-- Starting PIN: Staff/Admin only. type="password" keeps it out of the saved form draft. --}}
                <div x-show="role !== 'superadmin'" class="mt-6 rounded-2xl border border-[#EEE6DC] bg-[#FCFAF7] p-5">
                    <p class="text-sm font-bold text-[#251C19]">{{ __('Starting PIN') }}</p>
                    <p class="mt-1 text-xs leading-5 text-[#8B7D75]">{{ __('Tell them this PIN in person. They use it once, then choose their own. :min to :max digits — a simple one like 1234 is fine here.', ['min' => $pinLength['min'], 'max' => $pinLength['max']]) }}</p>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 sm:max-w-md">
                        <div>
                            <x-input-label for="pin" :value="__('PIN')" />
                            <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="{{ $pinLength['max'] }}" autocomplete="new-password"
                                   x-bind:required="role !== 'superadmin'"
                                   class="block mt-1 w-full tracking-[0.4em] rounded-lg border-gray-300 shadow-sm focus:border-[#8A3330] focus:ring-[#8A3330]">
                        </div>
                        <div>
                            <x-input-label for="pin_confirmation" :value="__('Confirm PIN')" />
                            <input id="pin_confirmation" name="pin_confirmation" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="{{ $pinLength['max'] }}" autocomplete="new-password"
                                   x-bind:required="role !== 'superadmin'"
                                   class="block mt-1 w-full tracking-[0.4em] rounded-lg border-gray-300 shadow-sm focus:border-[#8A3330] focus:ring-[#8A3330]">
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('pin')" class="mt-2" />
                </div>

                <div class="mt-8 flex items-center justify-end gap-3 border-t border-[#EEE6DC] pt-6">
                    <a href="{{ route('superadmin.users.index') }}" class="text-sm font-semibold text-[#766860] hover:text-[#302521]">{{ __('Cancel') }}</a>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#8A3330] px-5 py-2.5 text-sm font-bold text-white shadow-[0_12px_24px_-16px_rgba(138,51,48,0.9)] transition hover:-translate-y-0.5 hover:bg-[#742927] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/15">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.3" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                        </svg>
                        <span x-show="role === 'superadmin'">{{ __('Send Invitation') }}</span>
                        <span x-show="role !== 'superadmin'">{{ __('Create Account') }}</span>
                    </button>
                </div>
            </form>
        </div>

        {{-- How it works --}}
        <div class="space-y-4">
            @if ($pendingCount > 0)
                <div class="flex items-center justify-between rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)]">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{{ __('Pending Invitations') }}</p>
                        <p class="mt-1 text-2xl font-black tracking-[-0.02em] text-[#251C19]">{{ $pendingCount }}</p>
                    </div>
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-[#8A3330]">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                    </span>
                </div>
            @endif

            <div x-show="role !== 'superadmin'" class="rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)]">
                <h3 class="text-sm font-bold text-[#251C19]">{{ __('How PIN sign-in works') }}</h3>
                <ol class="mt-4 space-y-4">
                    @foreach ([
                        __('You add their name, role and a starting PIN.'),
                        __('Tell them the starting PIN in person.'),
                        __('They tap their name on the sign-in screen and enter it.'),
                        __('They choose their own PIN right away. Only they know it.'),
                    ] as $index => $step)
                        <li class="flex gap-3">
                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-[#F3E1DC] text-xs font-bold text-[#8A3330]">{{ $index + 1 }}</span>
                            <p class="text-sm leading-6 text-[#6C5E57]">{{ $step }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div x-show="role === 'superadmin'" x-cloak class="rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)]">
                <h3 class="text-sm font-bold text-[#251C19]">{{ __('How invitation works') }}</h3>
                <ol class="mt-4 space-y-4">
                    @foreach ([
                        __('You send an invite with just a name, email, and role.'),
                        __('The user gets an email with a secure activation link.'),
                        __('They open the link and create their own password.'),
                        __('Their account becomes active and they can sign in.'),
                    ] as $index => $step)
                        <li class="flex gap-3">
                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-[#F3E1DC] text-xs font-bold text-[#8A3330]">{{ $index + 1 }}</span>
                            <p class="text-sm leading-6 text-[#6C5E57]">{{ $step }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div x-show="role === 'superadmin'" x-cloak class="rounded-2xl border border-[#EEE6DC] bg-[#FCFAF7] p-5">
                <div class="flex gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-[#8A3330]">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-4.5 w-4.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-bold text-[#251C19]">{{ __('Link expires in 7 days') }}</p>
                        <p class="mt-1 text-xs leading-5 text-[#8B7D75]">{{ __('If it expires before they set up their account, you can resend a fresh invitation from the users list.') }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)]">
                <div class="flex gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-[#8A3330]">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-4.5 w-4.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-bold text-[#251C19]">{{ __('Private by design') }}</p>
                        <p x-show="role === 'superadmin'" x-cloak class="mt-1 text-xs leading-5 text-[#8B7D75]">{{ __("They'll receive an email invitation to set their own password. No password is created or seen by you.") }}</p>
                        <p x-show="role !== 'superadmin'" class="mt-1 text-xs leading-5 text-[#8B7D75]">{{ __('The starting PIN works for one sign-in only. After that, their PIN is one you never see.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent invitations --}}
    <div class="mt-6">
        <div class="mb-3 flex items-center gap-2.5">
            <span class="h-5 w-1 rounded-full bg-[#8A3330]"></span>
            <h3 class="text-xs font-bold uppercase tracking-[0.16em] text-[#8A7B74]">{{ __('Recent Invitations') }}</h3>
        </div>

        @if ($recentInvitations->isEmpty())
            <x-empty-state
                :title="__('No pending invitations')"
                :description="__('Invitations you send will show up here until the user activates their account.')"
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
                                <th class="px-6 py-3.5 text-left text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{{ __('Sent') }}</th>
                                <th class="px-6 py-3.5 text-right text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#EEE5DC]">
                            @foreach ($recentInvitations as $invitee)
                                <tr class="transition hover:bg-[#FAF6EE]">
                                    <td class="px-6 py-3.5 text-sm font-bold text-[#251C19]">{{ $invitee->name }}</td>
                                    <td class="px-6 py-3.5 text-sm text-[#6C5E57]">{{ $invitee->email }}</td>
                                    <td class="px-6 py-3.5">
                                        <span class="inline-flex rounded-full border border-[#E6CBC3] bg-[#F8EAE6] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] text-[#8A3330]">
                                            {{ $invitee->role->label() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3.5">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] {{ $invitee->invitationStatus()->badgeClasses() }}">
                                            {{ $invitee->invitationStatus()->label() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3.5 text-sm text-[#9A8B84]">{{ $invitee->created_at->diffForHumans() }}</td>
                                    <td class="px-6 py-3.5 text-right">
                                        <form action="{{ route('superadmin.users.resend-invitation', $invitee) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-[#E5D9CC] bg-[#FCF9F5] px-3 py-2 text-xs font-bold text-[#6E5E57] transition hover:border-[#CDAEA4] hover:bg-[#F8EEEA] hover:text-[#8A3330]">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 010 12h-3" />
                                                </svg>
                                                {{ __('Resend') }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Mobile cards --}}
            <div class="space-y-3 sm:hidden">
                @foreach ($recentInvitations as $invitee)
                    <div class="overflow-hidden rounded-2xl border border-[#E5DDD0] bg-white p-4 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)]">
                        <div class="flex items-center gap-3">
                            <x-avatar :user="$invitee" class="h-11 w-11 shrink-0 text-sm" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-[#251C19]">{{ $invitee->name }}</p>
                                <p class="truncate text-xs text-[#8B7D75]">{{ $invitee->email }}</p>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-1.5">
                            <span class="inline-flex rounded-full border border-[#E6CBC3] bg-[#F8EAE6] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] text-[#8A3330]">
                                {{ $invitee->role->label() }}
                            </span>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] {{ $invitee->invitationStatus()->badgeClasses() }}">
                                {{ $invitee->invitationStatus()->label() }}
                            </span>
                            <span class="text-xs font-medium text-[#B0A49E]">{{ $invitee->created_at->diffForHumans() }}</span>
                        </div>

                        <div class="mt-4 flex items-center justify-end border-t border-[#EEE6DC] pt-3.5">
                            <form action="{{ route('superadmin.users.resend-invitation', $invitee) }}" method="POST">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-[#E5D9CC] bg-[#FCF9F5] px-3 py-2 text-xs font-bold text-[#6E5E57] transition hover:border-[#CDAEA4] hover:bg-[#F8EEEA] hover:text-[#8A3330]">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 010 12h-3" />
                                    </svg>
                                    {{ __('Resend') }}
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
