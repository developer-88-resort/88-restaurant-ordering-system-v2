// Reports: the reload icon beside a section's title refreshes that section
// alone. The page for the same date / range (the current URL) is fetched in
// the background and only the matching [data-report-section] card is swapped
// in — no full browser reload, the scroll position and every other section
// stay as they are. Alpine picks up the new markup by itself.
import { initReportPagination } from './report-pagination';

export function initReportSectionRefresh() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-report-refresh]');
        if (!button || button.disabled) return;

        const section = button.closest('[data-report-section]');
        if (!section) return;

        const key = section.dataset.reportSection;
        button.disabled = true;
        button.classList.add('is-refreshing');

        try {
            const response = await fetch(window.location.href, {
                headers: { Accept: 'text/html' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fresh = [...doc.querySelectorAll('[data-report-section]')].find((el) => el.dataset.reportSection === key);
            if (!fresh) throw new Error(`Section ${key} not found`);

            // Already on screen — no fade-in again.
            fresh.classList.remove('animate-fade-slide-up');
            fresh.querySelectorAll('.animate-fade-slide-up').forEach((el) => el.classList.remove('animate-fade-slide-up'));
            const replacement = document.importNode(fresh, true);
            section.replaceWith(replacement);
            initReportPagination(replacement);
        } catch (error) {
            console.error('Report section refresh failed', error);
            button.disabled = false;
            button.classList.remove('is-refreshing');
            button.classList.add('text-red-600');
            button.title = button.dataset.errorLabel || 'Could not refresh — try again';
        }
    });
}
