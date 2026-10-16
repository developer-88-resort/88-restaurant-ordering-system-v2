// Maintenance Mode switched on (App\Events\MaintenanceModeChanged on the public
// "system" channel): every open screen — staff, Kitchen, customers at a table
// QR — reloads straight onto the maintenance page instead of waiting for the
// next click. A Superadmin keeps working (the layouts mark them exempt), and
// the maintenance page itself checks for the switch back off on its own.
export function initMaintenanceWatch() {
    if (window.__maintenanceWatchStarted || !window.Echo) return;
    window.__maintenanceWatchStarted = true;

    const exempt = () => document.querySelector('meta[name="maintenance-exempt"]')?.content === '1';

    window.Echo.channel('system').listen('.MaintenanceModeChanged', (event) => {
        if (event.on && !exempt()) {
            window.location.reload();
        }
    });
}
