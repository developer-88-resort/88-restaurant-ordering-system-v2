// System-wide form draft auto-save (Superadmin/Admin/Staff forms), so an
// accidental refresh or Turbo navigation away doesn't lose in-progress input.
// Shared storage helpers are also used by the `x-persist` Alpine directive
// registered in app.js, for the handful of forms with a dynamic x-for array
// (menu item variants, the staff order cart) that this module's plain-DOM
// restore can't reach.

const ROOT = 'draft:';
const EXPIRY_MS = 24 * 60 * 60 * 1000; // 24h — staff may step away and resume later.

// Drafts are filed under whoever is signed in (layouts/app.blade.php's
// <meta name="auth-user">), so on a shared tablet the next person to sign in
// never gets the last one's unfinished order, even if signing out never got
// the chance to clear it (the server ended the session, the tab was closed).
function prefix() {
    const user = document.querySelector('meta[name="auth-user"]')?.content;

    return `${ROOT}${user ? `u${user}` : 'guest'}:`;
}

function draftKeys() {
    const keys = [];
    try {
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.startsWith(ROOT)) keys.push(key);
        }
    } catch (e) {
        // Storage unavailable: there are no drafts to find.
    }

    return keys;
}

function removeKeys(keys) {
    keys.forEach((key) => {
        try {
            localStorage.removeItem(key);
        } catch (e) {
            // Nothing more can be done about storage that refuses.
        }
    });
}

// Set once this page starts signing out: a save still on its debounce timer
// (the blur from tapping Sign out fires one) would otherwise write the draft
// straight back after it was cleared.
let writesStopped = false;

// Signing out: nothing typed so far survives for the next person.
export function clearAllDrafts() {
    writesStopped = true;
    removeKeys(draftKeys());
}

// A signed-in page drops every draft that isn't its own user's — drafts left
// by an earlier sign-in included. Runs before Alpine starts, so no x-persist
// can read one first.
export function clearOtherUsersDrafts() {
    if (!document.querySelector('meta[name="auth-user"]')) return;

    const own = prefix();
    removeKeys(draftKeys().filter((key) => !key.startsWith(own)));
}

function sanitize(value) {
    if (Array.isArray(value)) {
        return value.map(sanitize);
    }
    if (value && typeof value === 'object') {
        const out = {};
        for (const key in value) {
            // Object URLs from a previous page load are always dead on restore
            // (blob: URLs don't survive a refresh) — never persist them.
            if (key === 'imagePreview') continue;
            out[key] = sanitize(value[key]);
        }
        return out;
    }
    return value;
}

export function readDraft(key) {
    try {
        const raw = localStorage.getItem(prefix() + key);
        if (!raw) return null;

        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object' || !('data' in parsed)) return null;

        if (Date.now() - parsed.savedAt > EXPIRY_MS) {
            localStorage.removeItem(prefix() + key);
            return null;
        }

        return parsed.data;
    } catch (e) {
        localStorage.removeItem(prefix() + key);
        return null;
    }
}

export function writeDraft(key, data) {
    if (writesStopped) return;

    try {
        localStorage.setItem(prefix() + key, JSON.stringify({ savedAt: Date.now(), data: sanitize(data) }));
    } catch (e) {
        // Storage full/unavailable (private browsing, quota) — draft-saving is
        // a convenience on top of the real submit, never allowed to be fatal.
    }
}

export function clearDraft(key) {
    removeKeys([prefix() + key]);
}

const EXCLUDED_TYPES = new Set(['file', 'password', 'hidden']);

function isPersistableField(field) {
    return !!field.name && !EXCLUDED_TYPES.has(field.type) && !field.closest('[data-draft-ignore]');
}

function serializeForm(form) {
    const data = {};

    Array.from(form.elements).forEach((field) => {
        if (!isPersistableField(field)) return;

        if (field.type === 'checkbox') {
            if (field.name.endsWith('[]')) {
                if (!Array.isArray(data[field.name])) data[field.name] = [];
                if (field.checked) data[field.name].push(field.value);
            } else {
                data[field.name] = field.checked;
            }
        } else if (field.type === 'radio') {
            if (field.checked) data[field.name] = field.value;
        } else if (field.tagName === 'SELECT' && field.multiple) {
            data[field.name] = Array.from(field.selectedOptions).map((option) => option.value);
        } else {
            data[field.name] = field.value;
        }
    });

    return data;
}

function restoreForm(form, data) {
    Array.from(form.elements).forEach((field) => {
        if (!isPersistableField(field)) return;
        if (!(field.name in data)) return;

        const value = data[field.name];

        if (field.type === 'checkbox') {
            field.checked = field.name.endsWith('[]')
                ? Array.isArray(value) && value.includes(field.value)
                : !!value;
        } else if (field.type === 'radio') {
            field.checked = field.value === value;
        } else if (field.tagName === 'SELECT' && field.multiple) {
            const selected = Array.isArray(value) ? value : [];
            Array.from(field.options).forEach((option) => { option.selected = selected.includes(option.value); });
        } else {
            field.value = value ?? '';
        }

        // Bubbling input+change lets any Alpine x-model on the same element
        // (e.g. menu-items' Sort Order) pick up the restored value too.
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    });
}

function debounce(fn, wait) {
    let timer = null;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
}

function wireForm(form) {
    const key = form.dataset.draftKey;
    if (!key || form.dataset.draftWired === '1') return;
    form.dataset.draftWired = '1';

    const draft = readDraft(key);
    if (draft) restoreForm(form, draft);

    const save = debounce(() => writeDraft(key, serializeForm(form)), 300);
    form.addEventListener('input', save);
    form.addEventListener('change', save);
    form.addEventListener('submit', () => clearDraft(key));
}

export function initDraftPersistence() {
    document.addEventListener('turbo:load', () => {
        // Deferred one tick (a macrotask, after any pending microtasks) so
        // Alpine's own MutationObserver-driven init of the newly-swapped-in
        // page has already run first — otherwise Alpine would overwrite our
        // restored value with its literal initial data right after us, for
        // any field that's also bound with x-model.
        setTimeout(() => {
            document.querySelectorAll('form[data-draft-key]').forEach(wireForm);
        }, 0);
    });
}
