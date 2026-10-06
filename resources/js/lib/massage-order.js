// New Massage Order (resources/views/massage/orders/create.blade.php), laid
// out like the restaurant's New Order: tap a service card to add it, adjust
// quantities in the Order Summary, enter the room (or guest), place it.
// A service with variants or add-ons opens a picker first (which variant —
// e.g. 1 hr — and which add-ons, e.g. Hot Stone). Prices here are only a
// preview; the server prices the order itself.
export function massageOrderForm(config) {
    const services = config.services ?? [];
    const byId = Object.fromEntries(services.map((service) => [service.id, service]));

    const lineKey = (serviceId, variantId, addOns) =>
        `${serviceId}|${variantId ?? 0}|${addOns.map((a) => `${a.id}x${a.qty}`).sort().join(',')}`;

    const buildLine = (service, variant, addOns, qty) => ({
        key: lineKey(service.id, variant?.id, addOns),
        id: service.id,
        variantId: variant?.id ?? null,
        name: variant ? `${service.name} — ${variant.name}` : service.name,
        unitPrice: variant ? variant.price : service.price,
        addOns,
        qty,
    });

    // A form sent back with errors keeps what was picked.
    const restored = (config.initial ?? [])
        .map((row) => {
            const service = byId[row.service_id];
            if (!service) return null;
            const variant = service.variants.find((v) => v.id === row.variant_id) ?? null;
            const addOns = (row.add_ons ?? [])
                .map((a) => {
                    const addOn = service.add_ons.find((x) => x.id === a.id);
                    return addOn ? { id: addOn.id, name: addOn.name, price: addOn.price, qty: a.quantity } : null;
                })
                .filter(Boolean);
            return buildLine(service, variant, addOns, row.quantity);
        })
        .filter(Boolean);

    return {
        services,
        cart: restored,
        search: '',
        roomNumber: config.roomNumber ?? '',
        guestName: config.guestName ?? '',
        summaryOpen: false,
        submitting: false,
        picker: null,

        matchesSearch(text) {
            const terms = this.search.trim().toLowerCase().split(/\s+/).filter(Boolean);
            const haystack = String(text).toLowerCase();
            return terms.every((term) => haystack.includes(term));
        },

        get hasMatches() {
            return this.services.some((service) => this.matchesSearch(service.name));
        },

        // Tapping a service card.
        tap(id) {
            const service = byId[id];
            if (!service) return;
            if (service.variants.length || service.add_ons.length) {
                this.openPicker(service);
            } else {
                this.addLine(buildLine(service, null, [], 1));
            }
        },

        openPicker(service) {
            const variant = service.variants.find((v) => v.is_default) ?? service.variants[0] ?? null;
            this.picker = {
                service,
                variantId: variant?.id ?? null,
                addOnQty: Object.fromEntries(service.add_ons.map((a) => [a.id, 0])),
                qty: 1,
            };
        },

        closePicker() {
            this.picker = null;
        },

        get pickerVariant() {
            return this.picker?.service.variants.find((v) => v.id === this.picker.variantId) ?? null;
        },

        get pickerAddOns() {
            if (!this.picker) return [];
            return this.picker.service.add_ons
                .filter((a) => this.picker.addOnQty[a.id] > 0)
                .map((a) => ({ id: a.id, name: a.name, price: a.price, qty: this.picker.addOnQty[a.id] }));
        },

        get pickerTotal() {
            if (!this.picker) return 0;
            const base = this.pickerVariant ? this.pickerVariant.price : this.picker.service.price;
            const extras = this.pickerAddOns.reduce((sum, a) => sum + a.price * a.qty, 0);
            return (base + extras) * this.picker.qty;
        },

        get pickerReady() {
            return !!this.picker && (this.picker.service.variants.length === 0 || this.pickerVariant !== null);
        },

        changeAddOn(id, delta) {
            this.picker.addOnQty[id] = Math.max(0, Math.min(20, (this.picker.addOnQty[id] ?? 0) + delta));
        },

        changePickerQty(delta) {
            this.picker.qty = Math.max(1, Math.min(20, this.picker.qty + delta));
        },

        confirmPicker() {
            if (!this.pickerReady) return;
            this.addLine(buildLine(this.picker.service, this.pickerVariant, this.pickerAddOns, this.picker.qty));
            this.closePicker();
        },

        addLine(line) {
            const existing = this.cart.find((row) => row.key === line.key);
            if (existing) {
                existing.qty += line.qty;
            } else {
                this.cart.push(line);
            }
        },

        // Price of one massage on a line, add-ons included.
        unitTotal(line) {
            return line.unitPrice + line.addOns.reduce((sum, a) => sum + a.price * a.qty, 0);
        },

        lineTotal(line) {
            return this.unitTotal(line) * line.qty;
        },

        quantity(id) {
            return this.cart.filter((row) => row.id === id).reduce((sum, row) => sum + row.qty, 0);
        },

        increment(index) {
            this.cart[index].qty += 1;
        },

        decrement(index) {
            this.cart[index].qty -= 1;
            if (this.cart[index].qty <= 0) this.cart.splice(index, 1);
        },

        clearCart() {
            this.cart = [];
        },

        toggleSummary() {
            this.summaryOpen = !this.summaryOpen;
            if (this.summaryOpen) {
                this.$nextTick(() => document.getElementById('order-summary')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            }
        },

        get cartCount() {
            return this.cart.reduce((sum, line) => sum + line.qty, 0);
        },

        get isEmpty() {
            return this.cart.length === 0;
        },

        get total() {
            return this.cart.reduce((sum, line) => sum + this.lineTotal(line), 0);
        },

        get hasGuest() {
            return this.roomNumber.trim() !== '' || this.guestName.trim() !== '';
        },

        get guestLabel() {
            return [this.roomNumber.trim() ? config.roomLabel.replace(':number', this.roomNumber.trim()) : '', this.guestName.trim()].filter(Boolean).join(' · ');
        },

        get canSubmit() {
            return !this.isEmpty && this.hasGuest;
        },

        submit() {
            if (!this.canSubmit || this.submitting) return;
            this.submitting = true;
            this.$root.submit();
        },

        formatMoney(amount) {
            return '₱' + Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    };
}
