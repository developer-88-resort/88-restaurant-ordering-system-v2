// Registers a one-shot cleanup that runs right before Turbo Drive caches the
// current page (i.e. right when navigating away). Needed on both the
// Blade+Alpine side (app.js, via window.turboCleanup for x-init strings) and
// the Inertia+React side (app.jsx, imported directly): Turbo swaps the
// document without unmounting React or running Alpine's own teardown, so any
// Echo listener/setInterval started on a page must be paired with this or it
// keeps running (and stacking duplicates) after the user navigates away.
export function turboCleanup(fn) {
    document.addEventListener('turbo:before-cache', fn, { once: true });
}
