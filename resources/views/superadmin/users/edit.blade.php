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
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM16.862 4.487 19.5 7.125" />
                    </svg>
                </div>

                <div>
                    <h2 class="text-xl font-bold tracking-[-0.025em] text-white sm:text-2xl">
                        {{ __('Edit Account') }}
                    </h2>
                    <p class="mt-1.5 max-w-md text-sm leading-6 text-white/55">
                        {{ __('Editing :name.', ['name' => $user->name]) }}
                    </p>
                </div>
            </div>
        </section>
    </x-slot>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="rounded-2xl border border-[#E5DDD0] bg-white p-6 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)] sm:p-8">
            <form method="POST" action="{{ route('superadmin.users.update', $user) }}" data-draft-key="superadmin-user-edit-{{ $user->id }}">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name', $user->name)" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input id="email" name="email" type="email" class="block mt-1 w-full" :value="old('email', $user->email)" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-5 max-w-xs">
                    <x-input-label for="role" :value="__('Role')" />
                    <span class="relative mt-1 block">
                        <select id="role" name="role" class="h-[2.6rem] w-full appearance-none bg-none rounded-lg border-gray-300 py-2 pl-3 pr-9 shadow-sm focus:border-[#8A3330] focus:ring-[#8A3330]" required>
                            <option value="superadmin" @selected(old('role', $user->role->value) === 'superadmin')>{{ __('Superadmin') }}</option>
                            <option value="admin" @selected(old('role', $user->role->value) === 'admin')>{{ __('Admin') }}</option>
                            <option value="staff" @selected(old('role', $user->role->value) === 'staff')>{{ __('Staff') }}</option>
                        </select>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#9B8C84]" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6.75 9 5.25 5.25L17.25 9" />
                        </svg>
                    </span>
                    <x-input-error :messages="$errors->get('role')" class="mt-2" />
                </div>

                <div class="mt-8 flex items-center justify-end gap-3 border-t border-[#EEE6DC] pt-6">
                    <a href="{{ route('superadmin.users.index') }}" class="text-sm font-semibold text-[#766860] hover:text-[#302521]">{{ __('Cancel') }}</a>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#8A3330] px-5 py-2.5 text-sm font-bold text-white shadow-[0_12px_24px_-16px_rgba(138,51,48,0.9)] transition hover:-translate-y-0.5 hover:bg-[#742927] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/15">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.3" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        {{ __('Save Changes') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="space-y-4">
            <div class="rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)]">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-[#8A3330]">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-4.5 w-4.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                    </span>
                    <h3 class="text-sm font-bold text-[#251C19]">{{ __('Password') }}</h3>
                </div>

                @if ($user->isPendingActivation())
                    <p class="mt-3 text-xs leading-5 text-[#8B7D75]">{{ __("This account hasn't been activated yet. Resend the invitation email so they can set their own password.") }}</p>

                    <form method="POST" action="{{ route('superadmin.users.resend-invitation', $user) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-[#E5D9CC] bg-[#FCF9F5] px-3 py-2.5 text-xs font-bold text-[#6E5E57] transition hover:border-[#CDAEA4] hover:bg-[#F8EEEA] hover:text-[#8A3330]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 010 12h-3" />
                            </svg>
                            {{ __('Resend Invitation') }}
                        </button>
                    </form>
                @else
                    <p class="mt-3 text-xs leading-5 text-[#8B7D75]">{{ __("For security, passwords can only be set by the account owner. Send them a reset link instead of choosing a password for them.") }}</p>

                    <form method="POST" action="{{ route('superadmin.users.send-password-reset', $user) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-[#E5D9CC] bg-[#FCF9F5] px-3 py-2.5 text-xs font-bold text-[#6E5E57] transition hover:border-[#CDAEA4] hover:bg-[#F8EEEA] hover:text-[#8A3330]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 010 12h-3" />
                            </svg>
                            {{ __('Send Password Reset Link') }}
                        </button>
                    </form>
                @endif
            </div>

            <div class="rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)]">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl {{ $user->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-4.5 w-4.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                        </svg>
                    </span>
                    <h3 class="text-sm font-bold text-[#251C19]">{{ __('Account Status') }}</h3>
                </div>

                @if ($user->is_active)
                    <p class="mt-3 text-xs leading-5 text-[#8B7D75]">{{ __('This account is active and can sign in. Deactivating keeps their record for audit and history, but blocks access.') }}</p>

                    @if ($isLastActiveSuperadmin)
                        <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs font-semibold leading-5 text-amber-700">{{ __('You cannot deactivate the last remaining Superadmin.') }}</p>
                    @else
                        <x-confirm-form
                            :action="route('superadmin.users.deactivate', $user)"
                            method="POST"
                            class="mt-4 block"
                            :title="__('Deactivate this account?')"
                            :message="__(':name will no longer be able to sign in. Their account and history are kept and can be reactivated anytime.', ['name' => $user->name])"
                            :confirm-label="__('Deactivate')"
                        >
                            <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-red-100 bg-white px-3 py-2.5 text-xs font-bold text-red-600 transition hover:border-red-200 hover:bg-red-50">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                                {{ __('Deactivate') }}
                            </button>
                        </x-confirm-form>
                    @endif
                @else
                    <p class="mt-3 text-xs leading-5 text-[#8B7D75]">{{ __('This account is deactivated and cannot sign in. Reactivate to restore access.') }}</p>

                    <form method="POST" action="{{ route('superadmin.users.reactivate', $user) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-xs font-bold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-100">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            {{ __('Reactivate') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
