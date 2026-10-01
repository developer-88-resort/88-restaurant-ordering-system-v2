import './bootstrap';
import '@hotwired/turbo';

import Alpine from 'alpinejs';
import { initDraftPersistence, readDraft, writeDraft, clearDraft, clearOtherUsersDrafts } from './draft-persistence';
import { turboCleanup } from './lib/turbo-cleanup';
import { orderPayment } from './lib/order-payment';
import { kitchenCancelDialog, kitchenBoardUpdated } from './lib/kitchen-cancel-dialog';
import { kitchenDirectPrint } from './lib/kitchen-direct-print';
import { ordersBrowser } from './lib/orders-browser';
import { kitchenSlipDiscount } from './lib/kitchen-slip-discount';
import { confirmOrderBeforePlacing } from './lib/order-confirm';
import { showFlashAlert } from './lib/flash-alert';
import { initIdleTimeout } from './lib/idle-timeout';
import { initSessionGuard } from './lib/session-guard';

window.Alpine = Alpine;

Alpine.data('orderPayment', orderPayment);
Alpine.data('kitchenCancelDialog', kitchenCancelDialog);
Alpine.data('kitchenDirectPrint', kitchenDirectPrint);
Alpine.data('ordersBrowser', ordersBrowser);
Alpine.data('kitchenSlipDiscount', kitchenSlipDiscount);

// Called from the Kitchen Display's x-init Echo listener (a bare global
// there, like turboCleanup below).
window.kitchenBoardUpdated = kitchenBoardUpdated;

// x-persist="{ key: 'unique-name', paths: ['someArray', 'someFlag'] }"
// Narrow counterpart to draft-persistence.js's generic <form data-draft-key>
// mechanism, for the handful of forms where an Alpine x-for array (menu item
// variants, the staff order cart) needs its rows
// restored into Alpine's reactive data directly — the DOM rows for those
// don't exist until Alpine renders them, so there's nothing for the generic
// DOM-scraping mechanism to restore values onto.
Alpine.directive('persist', (el, { expression }, { effect, cleanup }) => {
    const config = Alpine.evaluate(el, expression);
    if (!config || !config.key || !Array.isArray(config.paths)) return;

    const { key, paths } = config;
    const data = Alpine.$data(el);

    const draft = readDraft(key);
    if (draft) {
        paths.forEach((path) => {
            if (Object.prototype.hasOwnProperty.call(draft, path)) {
                data[path] = draft[path];
            }
        });
    }

    effect(() => {
        const snapshot = {};
        paths.forEach((path) => { snapshot[path] = data[path]; });
        writeDraft(key, snapshot);
    });

    const form = el.closest('form');
    if (form) {
        const clearOnSubmit = () => clearDraft(key);
        form.addEventListener('submit', clearOnSubmit);
        cleanup(() => form.removeEventListener('submit', clearOnSubmit));
    }
});

// Alpine's x-init strings reference turboCleanup as a bare global, so the
// shared helper (./lib/turbo-cleanup, also used directly by app.jsx's React
// pages) needs exposing on window here.
//
// Must be assigned before Alpine.start(): on a hard page load (not a Turbo
// navigation), the module script is deferred, so document.readyState is
// already past "loading" by the time this file runs — Alpine.start() then
// processes every x-init on the page synchronously, immediately. Any
// x-init that calls turboCleanup(...) would find it undefined if this
// assignment came after Alpine.start(), as it originally did here.
window.turboCleanup = turboCleanup;

// Called from the staff New Order page's Alpine scope (orders/create.blade.php).
window.confirmOrderBeforePlacing = confirmOrderBeforePlacing;

// Called from the flashed-message component (components/toast.blade.php).
window.showFlashAlert = showFlashAlert;

// Before Alpine.start(), so no x-persist restores another user's draft.
clearOtherUsersDrafts();
initSessionGuard();

Alpine.start();

initDraftPersistence();
initIdleTimeout();

import Swal from 'sweetalert2';

window.Swal = Swal;
