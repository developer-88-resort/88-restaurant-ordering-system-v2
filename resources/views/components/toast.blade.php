@php
    // Whatever the last request flashed: a success line, an error line, or both.
    $flashes = [];
    if (session('status')) {
        $flashes[] = ['type' => 'success', 'message' => session('status')];
    }
    if (session('error')) {
        $flashes[] = ['type' => 'error', 'message' => session('error')];
    }
@endphp

@if (! empty($flashes))
    {{-- Shown through SweetAlert2 (resources/js/lib/flash-alert.js): the old
         corner toast was too easy to miss at the counter. x-init runs once
         Alpine starts, by which point app.js has set the global up. --}}
    <div
        x-data
        x-init="
            window.flashAlertOkLabel = @js(__('OK'));
            @js($flashes).forEach((flash, index) => setTimeout(() => window.showFlashAlert(flash), index * 400));
        "
    ></div>
@endif
