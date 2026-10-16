// Reports: a long list shows its first ten records, with a button to show the
// rest ("Show all (42)") and to collapse it again ("Show less"), so "All time"
// or a busy day doesn't turn the page into one endless scroll. Every row is
// still in the page (totals and the PDF are unchanged) — only the display is
// shortened.
//
// A table opts in with data-paginate="10" (how many records to show first).
// Rows that belong together (a room's charges and its subtotal) share
// data-page-group; a subtotal row (data-page-subtotal) doesn't count as a
// record and shows only once all of its group's records are showing.

const barStyle = 'display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 20px;border-top:1px solid #F1F5F9;font-size:12px;color:#64748B;';
const buttonStyle = 'border:1px solid #E2E8F0;background:#fff;border-radius:8px;padding:5px 12px;font-weight:600;color:#8A3330;cursor:pointer;';

function groupsOf(tbody) {
    const groups = [];
    for (const row of tbody.rows) {
        const key = row.dataset.pageGroup;
        const last = groups[groups.length - 1];
        if (key && last && last.key === key) {
            last.rows.push(row);
        } else {
            groups.push({ key, rows: [row] });
        }
    }
    groups.forEach((group) => {
        group.records = group.rows.filter((row) => !('pageSubtotal' in row.dataset)).length;
    });
    return groups;
}

function setup(table) {
    if (table.dataset.paginated) return;
    table.dataset.paginated = '1';

    const limit = parseInt(table.dataset.paginate, 10) || 10;
    const tbody = table.tBodies[0];
    if (!tbody) return;

    const groups = groupsOf(tbody);
    const total = groups.reduce((sum, group) => sum + group.records, 0);
    if (total <= limit) return;

    // Exactly the first `limit` records; a subtotal row only once every
    // record of its group is showing (so it never sums hidden rows).
    const collapsedVisible = new Set();
    let seen = 0;
    for (const group of groups) {
        const records = group.rows.filter((row) => !('pageSubtotal' in row.dataset));
        records.forEach((row) => {
            if (seen < limit) collapsedVisible.add(row);
            seen++;
        });
        if (records.every((row) => collapsedVisible.has(row))) {
            group.rows.forEach((row) => collapsedVisible.add(row));
        }
    }
    const shownRecords = Math.min(limit, total);

    let expanded = false;
    const bar = document.createElement('div');
    bar.style.cssText = barStyle;
    bar.dataset.reportPager = '';
    const label = document.createElement('span');
    const button = document.createElement('button');
    button.type = 'button';
    button.style.cssText = buttonStyle;
    bar.append(label, button);

    const render = () => {
        for (const row of tbody.rows) {
            row.style.display = expanded || collapsedVisible.has(row) ? '' : 'none';
        }
        label.textContent = expanded ? `Showing all ${total}` : `Showing ${shownRecords} of ${total}`;
        button.textContent = expanded ? 'Show less' : `Show all (${total})`;
        button.setAttribute('aria-expanded', String(expanded));
    };

    button.addEventListener('click', () => {
        expanded = !expanded;
        render();
        if (!expanded) table.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });

    // Below the table's scroll wrapper, so it stays put when the table scrolls sideways.
    const anchor = table.closest('.overflow-x-auto') ?? table;
    anchor.after(bar);
    render();
}

export function initReportPagination(root = document) {
    root.querySelectorAll('table[data-paginate]').forEach(setup);
}

export function initReportPaginationOnLoad() {
    const run = () => initReportPagination(document);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
    document.addEventListener('turbo:load', run);
}
