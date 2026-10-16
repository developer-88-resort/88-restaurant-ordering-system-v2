// The Superadmin's floating assistant (bottom right). Plain DOM, no React, so
// the Blade pages (app.js) and the React pages (app.jsx) share it; the .jsx
// name is only so Tailwind scans its classes. It mounts into
// #assistant-root, which the layouts render for a Superadmin only, marked
// data-turbo-permanent so it survives Turbo page changes. Answers come from
// App\Http\Controllers\Superadmin\AssistantController (Gemini + the system's
// data); a change it wants to make shows as a card with Confirm / Cancel.

export const ASSISTANT_STORAGE_KEY = 'assistant.messages';
export const ASSISTANT_NUDGE_KEY = 'assistant.nudge-off';
export const ASSISTANT_ALERTS_KEY = 'assistant.alerts-dismissed';

const t = (key) => window.assistantStrings?.[key] ?? key;

// The quick-action chips (icons: Heroicons outline paths). `show` gets the
// departments the user works in (#assistant-root data-departments): the
// restaurant chips need Restaurant; Massage Today is for a Massage Admin.
const QUICK_ACTIONS = [
    {
        label: 'Today\'s Sales',
        question: 'How much are our sales today?',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />',
    },
    {
        label: 'Order Status',
        question: 'What orders are in progress right now?',
        show: (departments) => departments.includes('restaurant'),
        icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />',
    },
    {
        label: 'Menu & Prices',
        question: 'Give me a short summary of our menu and prices by category.',
        show: (departments) => departments.includes('restaurant'),
        icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M6 3v7.5M4 3v5.25a2 2 0 004 0V3M6 10.5V21M16.5 21V3c-1.657 0-3 2.239-3 6s1.343 5.25 3 5.25" />',
    },
    {
        label: 'Massage Today',
        question: 'How is the Massage department doing today?',
        show: (departments) => !departments.includes('restaurant'),
        icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" />',
    },
    {
        label: 'System Help',
        question: 'What can you help me with in the system?',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />',
    },
];

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
}

// Just enough Markdown for chat answers: **bold**, bullets and line breaks.
function format(text) {
    return escapeHtml(text)
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .split('\n')
        .map((line) => (/^\s*[-*•]\s+/.test(line) ? `<span class="block pl-3 -indent-3">• ${line.replace(/^\s*[-*•]\s+/, '')}</span>` : line))
        .join('<br>')
        .replace(/<\/span><br>/g, '</span>');
}

function readMessages() {
    try {
        return JSON.parse(sessionStorage.getItem(ASSISTANT_STORAGE_KEY) || '[]');
    } catch (e) {
        return [];
    }
}

function saveMessages(messages) {
    try {
        sessionStorage.setItem(ASSISTANT_STORAGE_KEY, JSON.stringify(messages.slice(-30)));
    } catch (e) {
        // Storage unavailable: the chat just won't survive a reload.
    }
}

export function initAssistantWidget() {
    const root = document.getElementById('assistant-root');
    if (!root || root.dataset.mounted) return;
    root.dataset.mounted = '1';

    const chatUrl = root.dataset.chatUrl;
    const departments = (root.dataset.departments || '').split(',');
    const confirmUrl = root.dataset.confirmUrl;
    const alertsUrl = root.dataset.alertsUrl;
    let messages = readMessages();
    let busy = false;

    root.innerHTML = `
        <button type="button" data-a="toggle" aria-label="${escapeHtml(t('Assistant'))}"
            class="mochi fixed bottom-4 right-4 z-[70] grid h-20 w-24 place-items-center rounded-2xl border-0 bg-transparent p-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
            <span class="mochi-shadow" aria-hidden="true"></span>
            <span data-a="badge" class="absolute right-2 top-1 z-10 hidden min-w-[1.25rem] rounded-full bg-[#C0392B] px-1.5 py-0.5 text-center text-[10px] font-bold leading-none text-white shadow ring-2 ring-white" aria-hidden="true"></span>
            <span class="mochi-body" aria-hidden="true">
                <img src="/images/mochi-assistant.png" alt="" draggable="false" class="pointer-events-none h-full w-full" />
                <span class="mochi-eye mochi-eye--left"></span>
                <span class="mochi-eye mochi-eye--right"></span>
            </span>
        </button>
        <div data-a="nudge" role="status" class="mochi-nudge fixed bottom-[6.25rem] right-4 z-[70] flex w-max max-w-[12.5rem] cursor-pointer items-start gap-1.5 rounded-2xl rounded-br-md border border-slate-200 bg-white py-2 pl-3 pr-1.5 text-[13px] font-medium leading-snug text-slate-800 shadow-lg shadow-slate-900/10">
            <span data-a="nudge-text" class="mochi-nudge-text min-h-[1.1rem]"></span>
            <button type="button" data-a="nudge-close" aria-label="${escapeHtml(t('Close'))}" class="grid h-5 w-5 shrink-0 place-items-center rounded-full text-[10px] text-slate-400 hover:bg-slate-100 hover:text-slate-700">✕</button>
        </div>
        <section data-a="panel" role="dialog" aria-label="${escapeHtml(t('Assistant'))}"
            class="fixed inset-x-3 bottom-24 z-[70] hidden max-h-[75vh] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/20 sm:inset-x-auto sm:right-5 sm:w-[24rem]">
            <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                <div class="flex items-center gap-2.5">
                    <img src="/images/mochi-assistant.png" alt="" aria-hidden="true" class="h-10 w-12 shrink-0 object-contain" />
                    <div>
                        <p class="flex items-center gap-2 text-sm font-semibold text-slate-900">Mochi
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700"><span class="relative flex h-1.5 w-1.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60 motion-reduce:animate-none"></span><span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span></span>${escapeHtml(t('Online'))}</span></p>
                        <p class="text-[11px] text-slate-500">${escapeHtml(t('88 Hot Spring Resort Assistant'))}</p>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" data-a="clear" class="rounded-lg px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-50 hover:text-slate-800">${escapeHtml(t('New chat'))}</button>
                    <button type="button" data-a="close" aria-label="${escapeHtml(t('Close'))}" class="grid h-8 w-8 place-items-center rounded-lg text-slate-500 hover:bg-slate-50 hover:text-slate-800">✕</button>
                </div>
            </header>
            <div data-a="list" class="min-h-[16rem] flex-1 space-y-3 overflow-y-auto bg-slate-50/60 px-4 py-4 text-sm"></div>
            <div data-a="file" class="hidden items-center gap-2 border-t border-slate-100 bg-white px-3 pt-2.5"></div>
            <form data-a="form" class="flex items-end gap-2 border-t border-slate-100 bg-white p-3">
                <input type="file" data-a="file-input" class="hidden" accept="image/jpeg,image/png,image/webp,application/pdf" />
                <button type="button" data-a="attach" class="grid h-11 w-9 shrink-0 place-items-center rounded-xl text-slate-500 hover:bg-slate-50 hover:text-slate-800" aria-label="${escapeHtml(t('Attach a photo or PDF'))}" title="${escapeHtml(t('Attach a photo or PDF'))}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" /></svg>
                </button>
                <textarea data-a="input" rows="1" maxlength="4000" placeholder="${escapeHtml(t('Ask anything, e.g. sales today?'))}"
                    class="max-h-32 min-h-[2.75rem] flex-1 resize-none rounded-xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"></textarea>
                <button type="submit" data-a="send" class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-slate-900 text-white hover:bg-slate-800 disabled:opacity-40" aria-label="${escapeHtml(t('Send'))}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
                </button>
            </form>
        </section>`;

    const $ = (name) => root.querySelector(`[data-a="${name}"]`);
    const panel = $('panel');
    const list = $('list');
    const input = $('input');
    const send = $('send');

    const bubble = (role, html) => {
        const wrap = document.createElement('div');
        wrap.className = role === 'user' ? 'flex justify-end' : 'flex justify-start';
        wrap.innerHTML = `<div class="${role === 'user'
            ? 'max-w-[85%] rounded-2xl rounded-br-md bg-slate-900 px-3.5 py-2.5 text-white'
            : 'max-w-[90%] rounded-2xl rounded-bl-md border border-slate-200 bg-white px-3.5 py-2.5 text-slate-800'} leading-relaxed">${html}</div>`;
        list.appendChild(wrap);
        list.scrollTop = list.scrollHeight;
        return wrap;
    };

    const DOTS = '<span class="inline-flex gap-1 py-1"><span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400"></span><span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400 [animation-delay:120ms]"></span><span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400 [animation-delay:240ms]"></span></span>';

    // Small chips at the bottom of the chat (under the greeting, then after
    // each answer); each asks a ready question. Only one set is ever shown.
    const quickActions = () => {
        list.querySelector('[data-quick]')?.remove();
        const wrap = document.createElement('div');
        wrap.dataset.quick = '1';
        wrap.className = 'mochi-chips flex flex-wrap gap-1.5 pl-1';
        wrap.innerHTML = QUICK_ACTIONS.map((action, index) => (action.show && !action.show(departments) ? '' : `
            <button type="button" data-q="${index}" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white py-1.5 pl-2 pr-3 text-xs font-medium text-slate-700 transition hover:border-[#8A3330]/40 hover:bg-[#F8EAE6]/60 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8A3330]/40">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5 text-[#8A3330]" aria-hidden="true">${action.icon}</svg>
                ${escapeHtml(t(action.label))}
            </button>`)).join('');
        wrap.querySelectorAll('[data-q]').forEach((button) => button.addEventListener('click', () => ask(t(QUICK_ACTIONS[button.dataset.q].question))));
        list.appendChild(wrap);
        list.scrollTop = list.scrollHeight;
    };

    // An empty chat: Mochi "types" first, then writes its greeting out and
    // offers the chips. Runs each time the chat is opened empty; greetRun
    // stops an older run (closed, reopened or a question sent meanwhile).
    const GREETING = "Hi! I'm Mochi, your 88 Hot Spring Resort assistant. Ask me about sales, orders, the menu, tables, Massage or how to do something in the system.";
    let greetRun = 0;
    let greetingText = null;
    let greetingDots = null;

    const greet = () => {
        const run = ++greetRun;
        const chars = Array.from(t(GREETING));
        list.innerHTML = '';
        if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
            bubble('model', escapeHtml(chars.join('')));
            quickActions();
            return;
        }
        greetingDots = bubble('model', DOTS);
        setTimeout(() => {
            if (run !== greetRun) return;
            greetingDots.remove();
            greetingDots = null;
            greetingText = bubble('model', '').firstElementChild;
            let shown = 0;
            const timer = setInterval(() => {
                if (run !== greetRun) {
                    clearInterval(timer);
                    return;
                }
                shown += 2;
                greetingText.textContent = chars.slice(0, shown).join('');
                list.scrollTop = list.scrollHeight;
                if (shown >= chars.length) {
                    clearInterval(timer);
                    greetingText = null;
                    quickActions();
                }
            }, 18);
        }, 650);
    };

    // A question sent while the greeting is still being written: finish it.
    const stopGreeting = () => {
        greetRun++;
        greetingDots?.remove();
        greetingDots = null;
        if (greetingText) greetingText.textContent = t(GREETING);
        greetingText = null;
    };

    const render = () => {
        if (messages.length === 0) {
            greet();
            return;
        }
        greetRun++;
        list.innerHTML = '';
        messages.forEach((message) => bubble(message.role, html(message)));
        quickActions();
    };

    // Buttons under an answer to the pages, orders or items it is about
    // (AssistantController sends them). A full page load (data-turbo off),
    // since Turbo can't swap between the Blade and the React pages.
    const linkButtons = (links) => (links?.length ? `<span class="mt-2.5 flex flex-wrap gap-1.5">${links.map((link) => `
        <a href="${escapeHtml(link.url)}" data-turbo="false" class="inline-flex items-center gap-1 rounded-lg border border-[#8A3330]/25 bg-[#F8EAE6]/60 px-2.5 py-1 text-xs font-semibold text-[#8A3330] no-underline transition hover:bg-[#F8EAE6] hover:border-[#8A3330]/50">${escapeHtml(link.label)}<span aria-hidden="true">→</span></a>`).join('')}</span>` : '');

    const fileChip = (file) => (file ? `<span class="mb-1.5 flex items-center gap-1.5 rounded-lg bg-white/15 px-2 py-1 text-xs font-medium">📎 ${escapeHtml(file)}</span>` : '');

    const html = (message) => (message.role === 'user' ? fileChip(message.file) + escapeHtml(message.text) : format(message.text) + linkButtons(message.links));

    // A photo or PDF for the next question (📎, paste or drop). Photos are
    // shrunk first: production takes small uploads, and Gemini reads a
    // 1600 px photo as well as the original. Sent once, never stored.
    const MAX_FILE = 5 * 1024 * 1024;
    let attachment = null;

    const showFile = () => {
        const box = $('file');
        box.classList.toggle('hidden', !attachment);
        box.classList.toggle('flex', !!attachment);
        box.innerHTML = attachment ? `
            ${attachment.preview ? `<img src="${attachment.preview}" alt="" class="h-11 w-11 rounded-lg border border-slate-200 object-cover" />` : '<span class="grid h-11 w-11 place-items-center rounded-lg border border-slate-200 bg-slate-50 text-[10px] font-bold text-[#8A3330]">PDF</span>'}
            <span class="min-w-0 flex-1 truncate text-xs text-slate-600">${escapeHtml(attachment.name)}</span>
            <button type="button" data-a="file-remove" aria-label="${escapeHtml(t('Remove'))}" class="grid h-7 w-7 place-items-center rounded-full text-xs text-slate-400 hover:bg-slate-100 hover:text-slate-700">✕</button>` : '';
        box.querySelector('[data-a=file-remove]')?.addEventListener('click', () => { attachment = null; showFile(); input.focus(); });
    };

    const readAsDataUrl = (blob) => new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(blob);
    });

    const shrink = (file) => new Promise((resolve, reject) => {
        const image = new Image();
        const url = URL.createObjectURL(file);
        image.onload = () => {
            const scale = Math.min(1, 1600 / Math.max(image.width, image.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(image.width * scale);
            canvas.height = Math.round(image.height * scale);
            const context = canvas.getContext('2d');
            context.fillStyle = '#fff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(image, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(url);
            resolve(canvas.toDataURL('image/jpeg', 0.85));
        };
        image.onerror = () => { URL.revokeObjectURL(url); reject(new Error('unreadable')); };
        image.src = url;
    });

    const attach = async (file) => {
        if (!file) return;
        const isPdf = file.type === 'application/pdf';
        if (!isPdf && !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            bubble('model', escapeHtml(t('Mochi can read a photo (JPG, PNG, WEBP) or a PDF of up to 5 MB.')));
            return;
        }
        try {
            const dataUrl = isPdf ? await readAsDataUrl(file) : await shrink(file);
            const data = dataUrl.slice(dataUrl.indexOf(',') + 1);
            if (data.length * 0.75 > MAX_FILE) {
                bubble('model', escapeHtml(t('Mochi can read a photo (JPG, PNG, WEBP) or a PDF of up to 5 MB.')));
                return;
            }
            attachment = { name: file.name || 'image.jpg', data, preview: isPdf ? null : dataUrl };
            showFile();
            input.focus();
        } catch (e) {
            bubble('model', escapeHtml(t('Mochi can read a photo (JPG, PNG, WEBP) or a PDF of up to 5 MB.')));
        }
    };

    const push = (role, text, links = [], file = null) => {
        const message = { role, text, ...(links.length ? { links } : {}), ...(file ? { file } : {}) };
        messages.push(message);
        saveMessages(messages);
        bubble(role, html(message));
    };

    const pendingCard = (action) => {
        const card = document.createElement('div');
        card.className = 'rounded-2xl border border-amber-200 bg-amber-50 px-3.5 py-3 text-amber-900';
        card.innerHTML = `<p class="text-xs font-semibold uppercase tracking-wide text-amber-700">${escapeHtml(t('Confirm this change'))}</p>
            <p class="mt-1 text-sm">${escapeHtml(action.summary)}</p>
            <div class="mt-3 flex gap-2">
                <button type="button" data-d="confirm" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">${escapeHtml(t('Confirm'))}</button>
                <button type="button" data-d="cancel" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">${escapeHtml(t('Cancel'))}</button>
            </div>`;
        card.querySelectorAll('button').forEach((button) => button.addEventListener('click', async () => {
            card.querySelectorAll('button').forEach((b) => { b.disabled = true; });
            try {
                const { data } = await window.axios.post(confirmUrl, { token: action.token, decision: button.dataset.d });
                card.remove();
                push('model', data.reply);
            } catch (error) {
                card.remove();
                push('model', error.response?.data?.reply ?? t('Something went wrong. Nothing was changed.'));
            }
            if (!busy) quickActions();
        }));
        list.appendChild(card);
        list.scrollTop = list.scrollHeight;
    };

    const ask = async (text) => {
        const file = attachment;
        if (busy || (!text.trim() && !file)) return;
        busy = true;
        send.disabled = true;
        stopGreeting();
        list.querySelector('[data-quick]')?.remove();
        $('toggle').classList.add('is-thinking');
        push('user', text.trim() || t('What is in this file?'), [], file?.name);
        attachment = null;
        showFile();
        const typing = bubble('model', DOTS);
        try {
            const { data } = await window.axios.post(chatUrl, {
                messages: messages.slice(-30).map(({ role, text }) => ({ role, text })),
                ...(file ? { attachment: { name: file.name, data: file.data } } : {}),
            });
            typing.remove();
            push('model', data.reply, data.links ?? []);
            (data.pending ?? []).forEach(pendingCard);
        } catch (error) {
            typing.remove();
            bubble('model', escapeHtml(error.response?.data?.error ?? (error.response?.status === 413 ? t('Mochi can read a photo (JPG, PNG, WEBP) or a PDF of up to 5 MB.') : t('The assistant is not available right now. Please try again.'))));
        } finally {
            busy = false;
            send.disabled = false;
            $('toggle').classList.remove('is-thinking');
            quickActions();
            input.focus();
        }
    };

    const open = (show) => {
        panel.classList.toggle('hidden', !show);
        panel.classList.toggle('flex', show);
        if (show) {
            if (messages.length === 0) greet();
            list.scrollTop = list.scrollHeight;
            input.focus();
        } else {
            greetRun++;
        }
    };

    const toggle = $('toggle');

    // Mochi's speech bubble, above its head. Real reminders come first
    // (AssistantTools::alertsFor, checked once a minute): clicking one asks
    // Mochi about it, and × hides that reminder until its count changes.
    // Without any, a short invitation (× hides those for the sign-in).
    const nudge = $('nudge');
    const nudgeText = $('nudge-text');
    const badge = $('badge');
    const NUDGES = [
        'Hi! Do you want to ask something?',
        'Need today\'s sales? Just ask me.',
        'I can check orders, the menu and tables for you.',
        'Not sure how to do something? Ask me!',
    ];
    let nudgeIndex = 0;
    let alertIndex = 0;
    let alerts = [];
    let current = null;
    let typing = null;
    let hideTimer = null;

    const stored = (key, fallback) => {
        try {
            return JSON.parse(sessionStorage.getItem(key)) ?? fallback;
        } catch (e) {
            return fallback;
        }
    };
    const store = (key, value) => {
        try {
            sessionStorage.setItem(key, JSON.stringify(value));
        } catch (e) {
            // Storage unavailable: it just comes back after a reload.
        }
    };
    const openAlerts = () => alerts.filter((alert) => !stored(ASSISTANT_ALERTS_KEY, []).includes(alert.key));

    const showBadge = () => {
        const count = openAlerts().length;
        badge.textContent = count;
        badge.classList.toggle('hidden', count === 0);
    };

    const fetchAlerts = async () => {
        if (!alertsUrl || document.hidden) return;
        try {
            const { data } = await window.axios.get(alertsUrl);
            alerts = data.alerts ?? [];
        } catch (e) {
            // Keep the last ones; the next check tries again.
        }
        showBadge();
    };

    const hideNudge = () => {
        clearInterval(typing);
        clearTimeout(hideTimer);
        nudge.classList.remove('is-shown');
    };

    const showNudge = () => {
        if (busy || document.hidden || !panel.classList.contains('hidden')) return;
        const pending = openAlerts();
        if (pending.length) {
            current = pending[alertIndex++ % pending.length];
        } else if (stored(ASSISTANT_NUDGE_KEY, 0) !== 1) {
            current = { text: t(NUDGES[nudgeIndex++ % NUDGES.length]) };
        } else {
            return;
        }
        const chars = Array.from(current.text);
        let shown = 0;
        nudgeText.textContent = '';
        nudge.classList.toggle('is-alert', !!current.key);
        nudge.classList.add('is-shown');
        toggle.classList.remove('is-greeting');
        void toggle.offsetWidth; // restart the wave
        toggle.classList.add('is-greeting');
        clearInterval(typing);
        typing = setInterval(() => {
            nudgeText.textContent = chars.slice(0, ++shown).join('');
            if (shown >= chars.length) clearInterval(typing);
        }, 30);
        clearTimeout(hideTimer);
        hideTimer = setTimeout(hideNudge, current.key ? 9000 : 6000);
    };

    // Back to floating once the wave is done.
    toggle.addEventListener('animationend', (event) => {
        if (event.animationName === 'mochi-wiggle') toggle.classList.remove('is-greeting');
    });
    fetchAlerts();
    setTimeout(showNudge, 2500);
    setInterval(() => fetchAlerts().then(showNudge), 60000);
    nudge.addEventListener('click', () => {
        hideNudge();
        open(true);
        if (current?.question) ask(current.question);
    });
    $('nudge-close').addEventListener('click', (event) => {
        event.stopPropagation();
        hideNudge();
        if (current?.key) {
            store(ASSISTANT_ALERTS_KEY, [...stored(ASSISTANT_ALERTS_KEY, []), current.key].slice(-50));
            showBadge();
        } else {
            store(ASSISTANT_NUDGE_KEY, 1);
        }
    });

    toggle.addEventListener('click', () => { hideNudge(); open(panel.classList.contains('hidden')); });
    $('close').addEventListener('click', () => open(false));
    $('clear').addEventListener('click', () => { messages = []; saveMessages(messages); render(); input.focus(); });
    $('form').addEventListener('submit', (event) => {
        event.preventDefault();
        const text = input.value;
        input.value = '';
        input.style.height = '';
        ask(text);
    });
    // On the Chat page Mochi sits higher, clear of its message box and Send
    // button (bottom right too). Rechecked after every page change: Turbo
    // for the Blade pages, Inertia for the React ones (Chat is one).
    const place = () => root.classList.toggle('mochi-lifted', /^\/chat(\/|$)/.test(window.location.pathname));
    place();
    document.addEventListener('turbo:load', place);
    document.addEventListener('inertia:navigate', place);
    window.addEventListener('popstate', place);

    // Attaching: the 📎 button, a pasted screenshot, or a file dropped on the chat.
    $('attach').addEventListener('click', () => $('file-input').click());
    $('file-input').addEventListener('change', (event) => {
        attach(event.target.files?.[0]);
        event.target.value = '';
    });
    input.addEventListener('paste', (event) => {
        const file = [...(event.clipboardData?.files ?? [])][0];
        if (file) {
            event.preventDefault();
            attach(file);
        }
    });
    panel.addEventListener('dragover', (event) => event.preventDefault());
    panel.addEventListener('drop', (event) => {
        event.preventDefault();
        attach(event.dataTransfer?.files?.[0]);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            $('form').requestSubmit();
        }
    });
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 128)}px`;
    });

    // An empty chat greets when it is opened (open()).
    if (messages.length) render();
}
