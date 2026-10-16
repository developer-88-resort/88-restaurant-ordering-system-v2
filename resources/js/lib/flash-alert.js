import Swal from 'sweetalert2';

// The "Order created successfully" line used to be a small toast in the corner
// that staff at the counter kept missing, so a flashed status/error now lands
// in the middle of the screen instead — on the Blade pages (components/
// toast.blade.php) and the React ones (Components/Toast.jsx) alike.
//
// A success closes itself — placing orders is a rhythm and should not need a
// tap — while an error waits to be dismissed, because it is the only place
// that tells you the action did NOT happen. The look comes from the
// .flash-swal* rules in resources/css/app.css (Tailwind doesn't scan .js).
export function showFlashAlert({ type = 'success', message = '' } = {}) {
    if (! message) return;

    const success = type !== 'error';

    Swal.fire({
        icon: success ? 'success' : 'error',
        iconColor: success ? '#10B981' : '#E11D48',
        iconHtml: success ? '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path class="flash-swal-check" pathLength="1" d="m5.5 12 4.25 4.25L18.5 7.5" /></svg>' : undefined,
        title: message,
        showConfirmButton: ! success,
        confirmButtonText: window.flashAlertOkLabel ?? 'OK',
        buttonsStyling: false,
        timer: success ? 2400 : undefined,
        timerProgressBar: false,
        allowOutsideClick: true,
        width: 380,
        showClass: { popup: 'flash-swal-in' },
        hideClass: { popup: 'flash-swal-out' },
        customClass: {
            container: 'flash-swal-backdrop',
            popup: `flash-swal ${success ? 'flash-swal--success' : 'flash-swal--error'}`,
            icon: 'flash-swal-icon',
            title: 'flash-swal-title',
            actions: 'flash-swal-actions',
            confirmButton: 'flash-swal-confirm',
        },
    });
}

// A yes/no question in the same look (e.g. switching Maintenance Mode on).
// Resolves true only when the confirm button was pressed.
export function confirmAction({ title = '', text = '', confirmLabel = 'OK', cancelLabel = 'Cancel', icon = 'warning' } = {}) {
    return Swal.fire({
        icon,
        iconColor: '#F59E0B',
        title,
        text,
        showCancelButton: true,
        reverseButtons: true,
        focusCancel: true,
        confirmButtonText: confirmLabel,
        cancelButtonText: cancelLabel,
        buttonsStyling: false,
        width: 400,
        showClass: { popup: 'flash-swal-in' },
        hideClass: { popup: 'flash-swal-out' },
        customClass: {
            container: 'flash-swal-backdrop',
            popup: 'flash-swal flash-swal--confirm',
            icon: 'flash-swal-icon',
            title: 'flash-swal-title',
            htmlContainer: 'flash-swal-text',
            actions: 'flash-swal-actions flash-swal-actions--pair',
            confirmButton: 'flash-swal-confirm',
            cancelButton: 'flash-swal-cancel',
        },
    }).then((result) => result.isConfirmed === true);
}
