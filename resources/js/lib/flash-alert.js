import Swal from 'sweetalert2';

// The "Order created successfully" line used to be a small toast in the corner
// that staff at the counter kept missing, so a flashed status/error now lands
// in the middle of the screen instead.
//
// Called from resources/views/components/toast.blade.php with whatever the
// session flashed. A success closes itself — placing orders is a rhythm and
// should not need a tap — while an error waits to be dismissed, because it is
// the only place that tells you the action did NOT happen.
export function showFlashAlert({ type = 'success', message = '' } = {}) {
    if (! message) return;

    const success = type !== 'error';

    Swal.fire({
        icon: success ? 'success' : 'error',
        title: message,
        showConfirmButton: ! success,
        confirmButtonText: window.flashAlertOkLabel ?? 'OK',
        confirmButtonColor: '#8A3330',
        timer: success ? 2400 : undefined,
        timerProgressBar: success,
        allowOutsideClick: true,
        width: 420,
    });
}
