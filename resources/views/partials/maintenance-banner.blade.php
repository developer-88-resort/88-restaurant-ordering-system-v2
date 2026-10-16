{{-- Shown to a Superadmin while Maintenance Mode is on (everyone else gets
     the maintenance page), so it is not left on by mistake. Same bar as
     Components/MaintenanceBanner.jsx on the React pages. --}}
@if (auth()->user()?->role === \App\Enums\UserRole::Superadmin && \App\Models\Setting::maintenanceIsOn())
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <span class="flex items-center gap-2">
            <span class="h-2 w-2 shrink-0 animate-pulse rounded-full bg-amber-500"></span>
            {{ __('Maintenance Mode is on — only Superadmin can use the system. Everyone else sees the maintenance page.') }}
        </span>
        <a href="{{ route('superadmin.settings.edit') }}" class="font-semibold underline underline-offset-4 hover:no-underline">{{ __('Turn it off in Settings') }}</a>
    </div>
@endif
