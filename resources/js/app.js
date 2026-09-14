import './bootstrap';
import '@hotwired/turbo';

import Alpine from 'alpinejs';
import { initDraftPersistence, readDraft, writeDraft, clearDraft } from './draft-persistence';
import { turboCleanup } from './lib/turbo-cleanup';
import { orderPayment } from './lib/order-payment';

window.Alpine = Alpine;

Alpine.data('orderPayment', orderPayment);

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

Alpine.start();

initDraftPersistence();

import Swal from 'sweetalert2';

window.Swal = Swal;
