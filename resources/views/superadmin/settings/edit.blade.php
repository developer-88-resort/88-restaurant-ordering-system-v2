<x-app-layout>
    <x-slot name="header">
        <div class="rounded-2xl border border-slate-200 bg-white px-6 py-6 shadow-sm">
        <h2 class="font-semibold text-2xl tracking-tight text-slate-900 leading-tight">
            {{ __('Settings') }}
        </h2>
        <p class="text-sm text-slate-500 mt-2">{{ __('These details may appear on receipts and the customer ordering page.') }}</p>
            </div>
    </x-slot>

    {{-- Maintenance Mode — its own forms (like Rooms' front-desk copy switch),
         so it never touches the settings form below. Switching it on opens an
         incident on the maintenance page's status section (asks first, since
         it locks every Admin, Staff and customer out); while it is on, updates
         are posted to that incident's timeline; switching it off resolves it. --}}
    @php
        $impacts = \App\Enums\IncidentImpact::cases();
        $statusChoices = \App\Enums\IncidentStatus::postable();
        $componentLabels = \App\Models\StatusIncident::componentLabels();
        // Static class names, so Tailwind sees them (colours match IncidentImpact::color()).
        $impactPill = ['maintenance' => 'has-[:checked]:bg-blue-500', 'minor' => 'has-[:checked]:bg-amber-500', 'major' => 'has-[:checked]:bg-rose-600'];
        $pill = 'cursor-pointer select-none rounded-full border px-3 py-1.5 text-xs font-semibold transition';
    @endphp
    <section class="mb-6 rounded-2xl border {{ $setting->maintenance_mode ? 'border-amber-300 bg-amber-50/60' : 'border-slate-200 bg-white' }} p-6 shadow-sm sm:p-8">
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $setting->maintenance_mode ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z" />
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-semibold text-gray-900">{{ __('Maintenance Mode') }}</h3>
                    @if ($setting->maintenance_mode)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>{{ __('On') }}
                        </span>
                    @else
                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">{{ __('Off') }}</span>
                    @endif
                </div>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">
                    {{ __('While it is on, every Admin, Staff and customer sees the "Website under maintenance" page. Only Superadmin can still use the system.') }}
                </p>
                <a href="{{ route('status') }}" target="_blank" data-turbo="false" class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-slate-700 underline-offset-4 hover:underline">{{ __('View status page') }} &nearr;</a>

                @if (! $setting->maintenance_mode)
                    {{-- Off: what the maintenance page will say once it is on. --}}
                    <form
                        method="POST"
                        action="{{ route('superadmin.settings.maintenance') }}"
                        x-data
                        @submit.prevent="
                            window.confirmAction({
                                title: @js(__('Turn on Maintenance Mode?')),
                                text: @js(__('Every Admin, Staff and customer will see the maintenance page until you turn it off. Only Superadmin can still use the system.')),
                                confirmLabel: @js(__('Turn on')),
                                cancelLabel: @js(__('Cancel')),
                            }).then((ok) => { if (ok) $el.submit(); });
                        "
                        class="mt-5 max-w-3xl space-y-5"
                    >
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="maintenance_mode" value="1">

                        <div>
                            <x-input-label for="incident_title" :value="__('Title')" />
                            <x-text-input id="incident_title" name="incident_title" type="text" maxlength="150" data-draft-ignore
                                class="mt-1.5 block w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300"
                                :value="old('incident_title')" :placeholder="__('Scheduled maintenance')" />
                            <x-input-error :messages="$errors->get('incident_title')" class="mt-2" />
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-700">{{ __('Impact') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($impacts as $impact)
                                    <label class="{{ $pill }} border-slate-200 bg-white text-slate-700 has-[:checked]:border-transparent has-[:checked]:text-white {{ $impactPill[$impact->value] }}">
                                        <input type="radio" name="incident_impact" value="{{ $impact->value }}" class="sr-only" data-draft-ignore @checked(old('incident_impact', 'maintenance') === $impact->value)>
                                        {{ $impact->label() }}
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('incident_impact')" class="mt-2" />
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-700">{{ __('Affected parts') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($componentLabels as $key => $label)
                                    <label class="{{ $pill }} border-slate-200 bg-white text-slate-700 has-[:checked]:border-slate-900 has-[:checked]:bg-slate-900 has-[:checked]:text-white">
                                        <input type="checkbox" name="incident_components[]" value="{{ $key }}" class="sr-only" data-draft-ignore
                                               @checked(in_array($key, old('incident_components', array_keys($componentLabels)), true))>
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('incident_components')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="maintenance_message" :value="__('Message (optional)')" />
                            <textarea id="maintenance_message" name="maintenance_message" rows="2" maxlength="500" data-draft-ignore
                                class="mt-1.5 block w-full rounded-lg border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                                placeholder="{{ __('This website is currently undergoing scheduled maintenance. We should be back shortly.') }}">{{ old('maintenance_message') }}</textarea>
                            <p class="mt-1.5 text-xs text-slate-500">{{ __('Shown under the title and as the first update on the maintenance page.') }}</p>
                            <x-input-error :messages="$errors->get('maintenance_message')" class="mt-2" />
                        </div>

                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-900 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                            {{ __('Turn on Maintenance Mode') }}
                        </button>
                    </form>
                @else
                    {{-- On: the open incident, a new update, and switching it off. --}}
                    @if ($activeIncident)
                        <div class="mt-5 max-w-3xl overflow-hidden rounded-xl border bg-white" style="border-color: {{ $activeIncident->impact->color() }}">
                            <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-sm font-semibold text-white" style="background: {{ $activeIncident->impact->color() }}">
                                <span>{{ $activeIncident->title }}</span>
                                <span class="text-xs font-medium text-white/80">{{ $activeIncident->impact->label() }}</span>
                            </div>
                            <ol class="max-h-64 divide-y divide-slate-100 overflow-y-auto px-4">
                                @foreach ($activeIncident->updates as $update)
                                    <li class="py-3 text-sm text-slate-700">
                                        <span class="font-bold text-slate-900">{{ $update->status->label() }}</span> - {{ $update->message }}
                                        <span class="mt-0.5 block text-xs text-slate-400">{{ \App\Support\StatusPage::stamp($update->created_at) }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>

                        <form method="POST" action="{{ route('superadmin.settings.status.incidents.updates', $activeIncident) }}" class="mt-4 max-w-3xl space-y-3">
                            @csrf
                            <p class="text-sm font-medium text-gray-700">{{ __('Post an update') }}</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($statusChoices as $choice)
                                    <label class="{{ $pill }} border-slate-200 bg-white text-slate-700 has-[:checked]:border-slate-900 has-[:checked]:bg-slate-900 has-[:checked]:text-white">
                                        <input type="radio" name="status" value="{{ $choice->value }}" class="sr-only" data-draft-ignore @checked(old('status', 'update') === $choice->value)>
                                        {{ $choice->label() }}
                                    </label>
                                @endforeach
                            </div>
                            <textarea name="message" rows="2" maxlength="1000" required data-draft-ignore
                                class="block w-full rounded-lg border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                                placeholder="{{ __('e.g. We found the cause and are fixing it now.') }}">{{ old('message') }}</textarea>
                            <x-input-error :messages="$errors->get('message')" class="mt-1" />
                            <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50">
                                {{ __('Post update') }}
                            </button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('superadmin.settings.maintenance') }}" class="mt-6 max-w-3xl space-y-3 border-t border-amber-200 pt-5">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="maintenance_mode" value="0">
                        <x-input-label for="resolve_message" :value="__('Closing message (optional)')" />
                        <textarea id="resolve_message" name="maintenance_message" rows="2" maxlength="500" data-draft-ignore
                            class="block w-full rounded-lg border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                            placeholder="{{ __('This incident has been resolved. Everything is working normally again.') }}"></textarea>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                            {{ __('Turn off Maintenance Mode') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </section>

    <div
        x-data="{
            resort_name: {{ Js::from(old('resort_name', $setting->resort_name)) }},
            address: {{ Js::from(old('address', $setting->address)) }},
            contact_number: {{ Js::from(old('contact_number', $setting->contact_number)) }},
            email: {{ Js::from(old('email', $setting->email)) }},
            opening_time: {{ Js::from(old('opening_time', optional($setting->opening_time)->format('H:i'))) }},
            closing_time: {{ Js::from(old('closing_time', optional($setting->closing_time)->format('H:i'))) }},
            tax_registration_type: {{ Js::from(old('tax_registration_type', $setting->tax_registration_type->value)) }},
            service_charge_enabled: {{ Js::from((bool) old('service_charge_enabled', $setting->service_charge_enabled)) }},
            saving: false,
        }"
        class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start"
    >
        {{-- Settings form — one form, one card, internal section dividers --}}
        <div class="lg:col-span-2 w-full">
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm">
                <form method="POST" action="{{ route('superadmin.settings.update') }}" @submit="saving = true" data-draft-key="superadmin-settings-edit">
                    @csrf
                    @method('PUT')

                    {{-- Resort Details --}}
                    <div class="p-6 sm:p-8">
                        <div class="flex items-start gap-3 mb-6">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ __('Resort Details') }}</h3>
                                <p class="mt-0.5 text-sm text-gray-500">{{ __('The name and address customers will see on receipts and the ordering page.') }}</p>
                            </div>
                        </div>

                        <div class="space-y-5">
                            <div>
                                <x-input-label for="resort_name" :value="__('Resort Name')" />
                                <x-text-input id="resort_name" name="resort_name" type="text" class="block mt-1.5 w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300" x-model="resort_name" required />
                                <x-input-error :messages="$errors->get('resort_name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="address" :value="__('Address')" />
                                <x-text-input id="address" name="address" type="text" class="block mt-1.5 w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300" x-model="address" />
                                <x-input-error :messages="$errors->get('address')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    {{-- Contact Information --}}
                    <div class="p-6 sm:p-8 border-t border-slate-200">
                        <div class="flex items-start gap-3 mb-6">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.733.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ __('Contact Information') }}</h3>
                                <p class="mt-0.5 text-sm text-gray-500">{{ __('How customers or staff can reach the resort.') }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <x-input-label for="contact_number" :value="__('Contact Number')" />
                                <x-text-input id="contact_number" name="contact_number" type="text" class="block mt-1.5 w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300" x-model="contact_number" />
                                <x-input-error :messages="$errors->get('contact_number')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="email" :value="__('Email')" />
                                <x-text-input id="email" name="email" type="email" class="block mt-1.5 w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300" x-model="email" />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    {{-- Business Hours --}}
                    <div class="p-6 sm:p-8 border-t border-slate-200">
                        <div class="flex items-start gap-3 mb-6">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ __('Business Hours') }}</h3>
                                <p class="mt-0.5 text-sm text-gray-500">{{ __('Shown in the live preview and on the customer menu page.') }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <x-input-label for="opening_time" :value="__('Opening Time')" />
                                <div class="relative mt-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500 pointer-events-none">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <x-text-input id="opening_time" name="opening_time" type="time" class="block w-full pl-9 h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300" x-model="opening_time" />
                                </div>
                                <x-input-error :messages="$errors->get('opening_time')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="closing_time" :value="__('Closing Time')" />
                                <div class="relative mt-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500 pointer-events-none">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <x-text-input id="closing_time" name="closing_time" type="time" class="block w-full pl-9 h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300" x-model="closing_time" />
                                </div>
                                <x-input-error :messages="$errors->get('closing_time')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    {{-- Tax & Invoice Settings --}}
                    <div class="p-6 sm:p-8 border-t border-slate-200">
                        <div class="flex items-start gap-3 mb-6">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185zM9.75 9h.008v.008H9.75V9zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 4.5h.008v.008h-.008V13.5zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ __('Tax & Invoice Settings') }}</h3>
                                <p class="mt-0.5 text-sm text-gray-500">{{ __('Used on official receipts/invoices. It\'s fine to leave BIR-specific fields blank until you have the official paperwork — invoices will still work, just without those details.') }}</p>
                            </div>
                        </div>

                        <div class="space-y-5">
                            <div>
                                <x-input-label for="bir_registered_name" :value="__('BIR Registered Business Name')" />
                                <x-text-input id="bir_registered_name" name="bir_registered_name" type="text" class="block mt-1.5 w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300" :value="old('bir_registered_name', $setting->bir_registered_name)" placeholder="{{ $setting->resort_name }}" />
                                <p class="mt-1 text-xs text-slate-500">{{ __('Leave blank to use the Resort Name above.') }}</p>
                                <x-input-error :messages="$errors->get('bir_registered_name')" class="mt-2" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <x-input-label for="tin" :value="__('TIN')" />
                                    <x-text-input id="tin" name="tin" type="text" class="block mt-1.5 w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300" :value="old('tin', $setting->tin)" placeholder="000-000-000-0000" />
                                    <x-input-error :messages="$errors->get('tin')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="branch_code" :value="__('Branch Code')" />
                                    <x-text-input id="branch_code" name="branch_code" type="text" class="block mt-1.5 w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300" :value="old('branch_code', $setting->branch_code)" />
                                    <x-input-error :messages="$errors->get('branch_code')" class="mt-2" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <x-input-label for="tax_registration_type" :value="__('Tax Registration Type')" />
                                    <select id="tax_registration_type" name="tax_registration_type" x-model="tax_registration_type"
                                            class="block mt-1.5 w-full border-slate-200 focus:border-slate-400 focus:ring-slate-300 rounded-lg shadow-sm">
                                        <option value="non_vat">{{ __('Non-VAT Registered') }}</option>
                                        <option value="vat">{{ __('VAT Registered') }}</option>
                                    </select>
                                    <p class="mt-1 text-xs text-slate-500" x-show="tax_registration_type === 'non_vat'">{{ __('Receipts will show "not valid for claim of input tax".') }}</p>
                                    <p class="mt-1 text-xs text-slate-500" x-show="tax_registration_type === 'vat'" x-cloak>{{ __('Receipts will show VATable/VAT-exempt/VAT amount rows.') }}</p>
                                    <x-input-error :messages="$errors->get('tax_registration_type')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="tax_rate" :value="__('Tax Rate (%)')" />
                                    <x-text-input id="tax_rate" name="tax_rate" type="number" step="0.01" min="0" max="100" class="block mt-1.5 w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300" :value="old('tax_rate', $setting->tax_rate)" />
                                    <x-input-error :messages="$errors->get('tax_rate')" class="mt-2" />
                                </div>
                            </div>

                            <div class="flex items-center">
                                <input id="prices_include_vat" name="prices_include_vat" type="checkbox" value="1"
                                       class="rounded border-slate-200 text-slate-700 shadow-sm focus:ring-slate-300"
                                       @checked(old('prices_include_vat', $setting->prices_include_vat))>
                                <label for="prices_include_vat" class="ms-2 text-sm text-gray-600">{{ __('Menu prices already include VAT') }}</label>
                            </div>

                            <div>
                                <x-input-label for="invoice_title" :value="__('Invoice Title')" />
                                <x-text-input id="invoice_title" name="invoice_title" type="text" class="block mt-1.5 w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300" :value="old('invoice_title', $setting->invoice_title)" placeholder="{{ $setting->resolvedInvoiceTitle() }}" />
                                <p class="mt-1 text-xs text-slate-500">{{ __('Must contain the word "Invoice". Leave blank to use the default for your tax registration type.') }}</p>
                                <x-input-error :messages="$errors->get('invoice_title')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="invoice_number_prefix" :value="__('Invoice Number Prefix')" />
                                <x-text-input id="invoice_number_prefix" name="invoice_number_prefix" type="text" class="block mt-1.5 w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300" :value="old('invoice_number_prefix', $setting->invoice_number_prefix)" placeholder="88HSR" />
                                <p class="mt-1 text-xs text-slate-500">{{ __('e.g. ":prefix" produces invoice numbers like \":prefix-001\", \":prefix-002\", etc. Changing this only affects invoices issued afterward — already-issued invoices keep their original number.', ['prefix' => $setting->invoice_number_prefix]) }}</p>
                                <x-input-error :messages="$errors->get('invoice_number_prefix')" class="mt-2" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <x-input-label for="bir_permit_number" :value="__('BIR Permit to Use Number')" />
                                    <x-text-input id="bir_permit_number" name="bir_permit_number" type="text" class="block mt-1.5 w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300" :value="old('bir_permit_number', $setting->bir_permit_number)" />
                                    <x-input-error :messages="$errors->get('bir_permit_number')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="atp_ocn_number" :value="__('Authority to Print / OCN')" />
                                    <x-text-input id="atp_ocn_number" name="atp_ocn_number" type="text" class="block mt-1.5 w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300" :value="old('atp_ocn_number', $setting->atp_ocn_number)" />
                                    <x-input-error :messages="$errors->get('atp_ocn_number')" class="mt-2" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                                <div>
                                    <x-input-label for="atp_ocn_date_issued" :value="__('ATP/OCN Date Issued')" />
                                    <x-text-input id="atp_ocn_date_issued" name="atp_ocn_date_issued" type="date" class="block mt-1.5 w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300" :value="old('atp_ocn_date_issued', optional($setting->atp_ocn_date_issued)->format('Y-m-d'))" />
                                    <x-input-error :messages="$errors->get('atp_ocn_date_issued')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="invoice_serial_from" :value="__('Approved Serial — From')" />
                                    <x-text-input id="invoice_serial_from" name="invoice_serial_from" type="text" class="block mt-1.5 w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300" :value="old('invoice_serial_from', $setting->invoice_serial_from)" />
                                    <x-input-error :messages="$errors->get('invoice_serial_from')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="invoice_serial_to" :value="__('Approved Serial — To')" />
                                    <x-text-input id="invoice_serial_to" name="invoice_serial_to" type="text" class="block mt-1.5 w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300" :value="old('invoice_serial_to', $setting->invoice_serial_to)" />
                                    <x-input-error :messages="$errors->get('invoice_serial_to')" class="mt-2" />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="invoice_footer_message" :value="__('Invoice Footer Message')" />
                                <textarea id="invoice_footer_message" name="invoice_footer_message" rows="2" placeholder="{{ $setting->resolvedFooterMessage() }}"
                                          class="mt-1.5 w-full text-sm rounded-lg border-slate-200 focus:border-slate-400 focus:ring-slate-300">{{ old('invoice_footer_message', $setting->invoice_footer_message) }}</textarea>
                                <x-input-error :messages="$errors->get('invoice_footer_message')" class="mt-2" />
                            </div>

                            <div class="pt-4 border-t border-dashed border-slate-200 space-y-4">
                                <div class="flex items-center">
                                    <input id="service_charge_enabled" name="service_charge_enabled" type="checkbox" value="1" x-model="service_charge_enabled"
                                           class="rounded border-slate-200 text-slate-700 shadow-sm focus:ring-slate-300">
                                    <label for="service_charge_enabled" class="ms-2 text-sm text-gray-600">{{ __('Charge a service charge') }}</label>
                                </div>

                                <div x-show="service_charge_enabled" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                    <div>
                                        <x-input-label for="service_charge_percent" :value="__('Service Charge (%)')" />
                                        <x-text-input id="service_charge_percent" name="service_charge_percent" type="number" step="0.01" min="0" max="100" class="block mt-1.5 w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300" :value="old('service_charge_percent', $setting->service_charge_percent)" />
                                        <x-input-error :messages="$errors->get('service_charge_percent')" class="mt-2" />
                                    </div>
                                    <div class="flex items-end pb-2.5">
                                        <div class="flex items-center">
                                            <input id="service_charge_taxable" name="service_charge_taxable" type="checkbox" value="1"
                                                   class="rounded border-slate-200 text-slate-700 shadow-sm focus:ring-slate-300"
                                                   @checked(old('service_charge_taxable', $setting->service_charge_taxable))>
                                            <label for="service_charge_taxable" class="ms-2 text-sm text-gray-600">{{ __('Subject to VAT') }}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Weighed Orders --}}
                            <div class="pt-5 border-t border-slate-200">
                                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">{{ __('Weighed Orders') }}</h3>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ __('The counter scale gives both the weight and the amount; staff key in what the display shows. These rules decide how far that amount may sit from the reference rate before someone has to explain.') }}
                                </p>

                                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-5">
                                    <div>
                                        <x-input-label for="weighed_variance_tolerance_amount" :value="__('Variance tolerance (₱)')" />
                                        <x-text-input id="weighed_variance_tolerance_amount" name="weighed_variance_tolerance_amount" type="number" step="0.01" min="0"
                                                      class="mt-1 block w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300"
                                                      :value="old('weighed_variance_tolerance_amount', $setting->weighed_variance_tolerance_amount)" required />
                                        <x-input-error class="mt-2" :messages="$errors->get('weighed_variance_tolerance_amount')" />
                                    </div>
                                    <div>
                                        <x-input-label for="weighed_variance_tolerance_percent" :value="__('Variance tolerance (%)')" />
                                        <x-text-input id="weighed_variance_tolerance_percent" name="weighed_variance_tolerance_percent" type="number" step="0.01" min="0" max="100"
                                                      class="mt-1 block w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300"
                                                      :value="old('weighed_variance_tolerance_percent', $setting->weighed_variance_tolerance_percent)" required />
                                        <x-input-error class="mt-2" :messages="$errors->get('weighed_variance_tolerance_percent')" />
                                    </div>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ __('How far the keyed amount may sit from the computed amount before a reason is required. Whichever of the two is larger is used, per line.') }}
                                </p>

                                <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
                                    <div>
                                        <x-input-label for="weighed_variance_hard_ceiling_percent" :value="__('Hard variance ceiling (%)')" />
                                        <x-text-input id="weighed_variance_hard_ceiling_percent" name="weighed_variance_hard_ceiling_percent" type="number" step="0.01" min="0" max="100"
                                                      class="mt-1 block w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300"
                                                      :value="old('weighed_variance_hard_ceiling_percent', $setting->weighed_variance_hard_ceiling_percent)" required />
                                        <p class="mt-1 text-xs text-slate-500">{{ __('Beyond this, a reason is not enough — it needs the price-override permission.') }}</p>
                                        <x-input-error class="mt-2" :messages="$errors->get('weighed_variance_hard_ceiling_percent')" />
                                    </div>
                                    <div>
                                        <x-input-label for="weighed_below_minimum_behavior" :value="__('Below minimum weight')" />
                                        <select id="weighed_below_minimum_behavior" name="weighed_below_minimum_behavior"
                                                class="mt-1 block w-full border-slate-200 focus:border-slate-400 focus:ring-slate-300 rounded-lg shadow-sm">
                                            <option value="block" @selected(old('weighed_below_minimum_behavior', $setting->weighed_below_minimum_behavior) === 'block')>{{ __('Block') }}</option>
                                            <option value="bill_at_minimum" @selected(old('weighed_below_minimum_behavior', $setting->weighed_below_minimum_behavior) === 'bill_at_minimum')>{{ __('Bill at minimum') }}</option>
                                        </select>
                                        <p class="mt-1 text-xs text-slate-500">{{ __('What happens when the weight is under the item\'s minimum.') }}</p>
                                        <x-input-error class="mt-2" :messages="$errors->get('weighed_below_minimum_behavior')" />
                                    </div>
                                </div>

                                <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
                                    <div>
                                        <x-input-label for="weighed_price_per_kilo_min" :value="__('Lowest allowed price per kilo (₱)')" />
                                        <x-text-input id="weighed_price_per_kilo_min" name="weighed_price_per_kilo_min" type="number" step="0.01" min="1"
                                                      class="mt-1 block w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300"
                                                      :value="old('weighed_price_per_kilo_min', $setting->weighed_price_per_kilo_min)" required />
                                        <x-input-error class="mt-2" :messages="$errors->get('weighed_price_per_kilo_min')" />
                                    </div>
                                    <div>
                                        <x-input-label for="weighed_price_per_kilo_max" :value="__('Highest allowed price per kilo (₱)')" />
                                        <x-text-input id="weighed_price_per_kilo_max" name="weighed_price_per_kilo_max" type="number" step="0.01" min="1"
                                                      class="mt-1 block w-full h-11 !rounded-lg !border-slate-200 focus:!border-slate-400 focus:!ring-slate-300"
                                                      :value="old('weighed_price_per_kilo_max', $setting->weighed_price_per_kilo_max)" required />
                                        <x-input-error class="mt-2" :messages="$errors->get('weighed_price_per_kilo_max')" />
                                    </div>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ __('A typo guard for the reference rate on the menu item form.') }}</p>

                                <div class="mt-5 space-y-3">
                                    <div class="flex items-center">
                                        <input id="weigh_customer_confirmation_enabled" name="weigh_customer_confirmation_enabled" type="checkbox" value="1"
                                               class="rounded border-slate-200 text-slate-700 shadow-sm focus:ring-slate-300"
                                               @checked(old('weigh_customer_confirmation_enabled', $setting->weigh_customer_confirmation_enabled))>
                                        <label for="weigh_customer_confirmation_enabled" class="ms-2 text-sm text-gray-600">{{ __('Require the customer to confirm each weighed item before the kitchen starts') }}</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input id="weighed_print_slip" name="weighed_print_slip" type="checkbox" value="1"
                                               class="rounded border-slate-200 text-slate-700 shadow-sm focus:ring-slate-300"
                                               @checked(old('weighed_print_slip', $setting->weighed_print_slip))>
                                        <label for="weighed_print_slip" class="ms-2 text-sm text-gray-600">{{ __('Print a weigh slip for each weighed item') }}</label>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-dashed border-slate-200">
                                <div class="flex items-center">
                                    <input id="reveal_full_discount_id_on_pdf" name="reveal_full_discount_id_on_pdf" type="checkbox" value="1"
                                           class="rounded border-slate-200 text-slate-700 shadow-sm focus:ring-slate-300"
                                           @checked(old('reveal_full_discount_id_on_pdf', $setting->reveal_full_discount_id_on_pdf))>
                                    <label for="reveal_full_discount_id_on_pdf" class="ms-2 text-sm text-gray-600">{{ __('Show the full Senior/PWD ID number on the downloadable PDF invoice') }}</label>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ __('The on-screen receipt always masks the ID number regardless of this setting — this only controls the official printed/PDF copy, where BIR typically requires the full ID for substantiation.') }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Actions footer --}}
                    <div class="px-6 sm:px-8 py-4 flex items-center justify-end bg-white border-t border-slate-100 rounded-b-2xl">
                        <button type="submit" x-bind:disabled="saving" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-900 shadow-sm transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 disabled:opacity-60 disabled:cursor-not-allowed">
                            <span x-show="!saving">{{ __('Save Changes') }}</span>
                            <span x-show="saving" x-cloak class="inline-flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="h-3.5 w-3.5 animate-spin">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                {{ __('Saving...') }}
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Live Preview --}}
        <div class="w-full lg:sticky lg:top-20">
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 sm:p-8">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500">{{ __('Live Preview') }}</h3>
                </div>

                <div class="border border-dashed border-slate-200 rounded-lg p-6 text-center bg-slate-50">
                    <p class="font-bold text-gray-900 break-words" x-text="resort_name || '{{ __('Resort Name') }}'"></p>
                    <p class="text-xs text-gray-500 mt-2 break-words" x-text="address || '{{ __('Resort address') }}'"></p>
                    <p class="text-xs text-gray-500 mt-1" x-text="contact_number || '{{ __('Contact number') }}'"></p>
                    <p class="text-xs text-gray-500 break-words" x-text="email || '{{ __('Email address') }}'"></p>
                    <p class="text-xs font-semibold text-slate-700 mt-3 pt-3 border-t border-dashed border-slate-200"
                       x-text="(opening_time && closing_time) ? ('{{ __('Open') }} ' + opening_time + ' – ' + closing_time) : '{{ __('Business hours') }}'"></p>
                </div>

                <p class="mt-4 text-xs text-slate-500">{{ __('This is how your resort info may look on printed receipts and the customer menu page.') }}</p>
            </div>

            {{-- Welcome QR --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 sm:p-8 mt-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-5 w-5">
                            <rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.5" />
                            <rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.5" />
                            <rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.5" />
                            <rect x="5.5" y="5.5" width="2" height="2" fill="currentColor" />
                            <rect x="16.5" y="5.5" width="2" height="2" fill="currentColor" />
                            <rect x="5.5" y="16.5" width="2" height="2" fill="currentColor" />
                            <rect x="14" y="14" width="3" height="3" fill="currentColor" />
                            <rect x="18" y="14" width="3" height="3" fill="currentColor" />
                            <rect x="14" y="18" width="3" height="3" fill="currentColor" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500">{{ __('Welcome QR Code') }}</h3>
                </div>

                <p class="text-sm text-gray-500">{{ __('One general QR you can post at the entrance/lobby — customers scan it, type their name, then pick to order takeout, choose a seat, call a staff, or browse the menu.') }}</p>

                <a href="{{ route('superadmin.welcome-qr.print') }}" target="_blank"
                   class="mt-4 inline-flex items-center justify-center gap-1.5 w-full min-h-11 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" />
                    </svg>
                    {{ __('Print / Download QR') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
