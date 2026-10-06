// Order Management's list: status buttons, location filter and search, all
// in the browser over the orders the page already carries (instant, no
// reload). Registered as Alpine.data('ordersBrowser', ...) in app.js and used
// as x-data="ordersBrowser(@js($config))" on orders/index.blade.php.
//
// config.orders: { [orderId]: { status, area, fields[], text, created, done } }
// built by OrderController@index.

// Kept per tab, so the live reload (any order changing anywhere) doesn't drop
// what the cashier was looking at. Cleared on sign-out (lib/session-guard.js).
export const ORDERS_BROWSER_KEYS = {
    status: 'orders.selectedStatus',
    area: 'orders.selectedArea',
    query: 'orders.query',
};

// Massage's Order Management keeps its own choices (its statuses differ).
export const MASSAGE_ORDERS_BROWSER_KEYS = {
    status: 'massageOrders.selectedStatus',
    area: 'massageOrders.selectedArea',
    query: 'massageOrders.query',
};

const FINISHED = ['completed', 'cancelled', 'paid'];

function readKept(key, fallback) {
    try {
        return sessionStorage.getItem(key) ?? fallback;
    } catch (e) {
        return fallback;
    }
}

function keep(key, value) {
    try {
        sessionStorage.setItem(key, value);
    } catch (e) {
        // Storage unavailable: the filter just won't survive a reload.
    }
}

export function ordersBrowser(config) {
    const KEYS = config.massage ? MASSAGE_ORDERS_BROWSER_KEYS : ORDERS_BROWSER_KEYS;
    const statuses = config.statuses ?? null;
    const keptStatus = readKept(KEYS.status, 'all');

    return {
        orders: config.orders ?? {},
        selectedStatus: statuses && keptStatus !== 'all' && !statuses.includes(keptStatus) ? 'all' : keptStatus,
        selectedArea: readKept(KEYS.area, 'all'),
        query: readKept(KEYS.query, ''),

        init() {
            // A kept location that no longer exists (area removed) falls back.
            if (this.selectedArea !== 'all' && !Object.values(this.orders).some((o) => o.area === this.selectedArea)
                && !(config.areaKeys ?? []).includes(this.selectedArea)) {
                this.selectedArea = 'all';
            }

            this.$watch('selectedStatus', (value) => { keep(KEYS.status, value); this.reorder(); });
            this.$watch('selectedArea', (value) => { keep(KEYS.area, value); this.reorder(); });
            this.$watch('query', (value) => { keep(KEYS.query, value); this.reorder(); });
            this.$nextTick(() => this.reorder());
        },

        // Every word typed has to appear somewhere in the order — more words,
        // narrower list, down to the one exact order.
        get terms() {
            return this.query.trim().toLowerCase().split(/\s+/).filter(Boolean);
        },

        matchesSearch(order) {
            return this.terms.every((term) => order.text.includes(term));
        },

        inArea(order) {
            return this.selectedArea === 'all' || order.area === this.selectedArea;
        },

        inStatus(order, status = this.selectedStatus) {
            return status === 'all' || order.status === status;
        },

        isVisible(id) {
            const order = this.orders[id];
            return !!order && this.inStatus(order) && this.inArea(order) && this.matchesSearch(order);
        },

        get visibleCount() {
            return Object.keys(this.orders).filter((id) => this.isVisible(id)).length;
        },

        get hasVisibleOrders() {
            return this.visibleCount > 0;
        },

        get isFiltering() {
            return this.selectedArea !== 'all' || this.terms.length > 0;
        },

        // Counts follow the other filters, so each button says how many it
        // would show right now.
        statusCount(status) {
            return Object.values(this.orders)
                .filter((o) => this.inStatus(o, status) && this.inArea(o) && this.matchesSearch(o)).length;
        },

        areaCount(area) {
            return Object.values(this.orders)
                .filter((o) => (area === 'all' || o.area === area) && this.inStatus(o) && this.matchesSearch(o)).length;
        },

        // How closely the whole search matches one of the order's values:
        // "table 1" ranks Table 1 above Table 10 and Table 12.
        score(order) {
            const q = this.query.trim().toLowerCase();
            if (!q) return 0;
            if (order.fields.includes(q)) return 3;
            if (order.fields.some((field) => field.startsWith(q))) return 2;
            if (order.fields.some((field) => field.includes(q))) return 1;
            return 0;
        },

        // Newest first. A Completed or Cancelled list goes by when the order
        // was finished, so the most recently completed is on top.
        sortTime(order) {
            return FINISHED.includes(this.selectedStatus) ? order.done : order.created;
        },

        reorder() {
            const ids = Object.keys(this.orders).sort((a, b) => {
                const A = this.orders[a];
                const B = this.orders[b];
                return (this.score(B) - this.score(A)) || (this.sortTime(B) - this.sortTime(A)) || (b - a);
            });

            this.$root.querySelectorAll('[data-order-list]').forEach((list) => {
                ids.forEach((id) => {
                    const row = list.querySelector(`:scope > [data-order-row="${id}"]`);
                    if (row) list.appendChild(row);
                });
            });
        },

        clearFilters() {
            this.query = '';
            this.selectedArea = 'all';
            this.selectedStatus = 'all';
        },
    };
}
