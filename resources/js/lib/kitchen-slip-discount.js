import { holdBoardReload, releaseBoardReload, reloadBoardNow } from './kitchen-cancel-dialog';

// Registered as Alpine.data('kitchenSlipDiscount', ...) in app.js and used by
// kitchen/partials/slip-discount-dialog.blade.php. Each card's Discount button
// dispatches `kitchen-slip-discount` with that slip's lines and current
// discounts.
//
// Slip only: what is picked here changes the printed order slip, never the
// receipt. The preview mirrors App\Services\Printing\OrderSlipTotals — no
// VAT, a discount is a plain share of the price — but the server works the
// figures out again for itself; this is only so staff see them before saving.
export function kitchenSlipDiscount(config) {
    return {
        rules: config.rules,
        text: config.text,

        isOpen: false,
        order: null,
        selections: {},
        submitting: false,
        error: '',

        open(order) {
            this.order = order;
            this.selections = {};
            for (const entry of order.current ?? []) {
                if (!this.rules.some((rule) => rule.id === entry.rule_id)) continue;
                this.selections[entry.rule_id] = {
                    value: entry.value ?? '',
                    eligMode: entry.eligible_amount ? 'amount' : 'items',
                    itemIds: (entry.item_ids ?? []).map(Number),
                    eligibleAmount: entry.eligible_amount ?? '',
                    qualifiedName: entry.qualified_name ?? '',
                };
            }
            this.error = '';
            this.submitting = false;
            this.isOpen = true;
            holdBoardReload();
        },

        close() {
            this.isOpen = false;
            releaseBoardReload();
        },

        get selectedRules() {
            return this.rules.filter((rule) => this.selections[rule.id]);
        },

        // Same as checkout: an exclusive discount can't sit with another one.
        isDisabled(rule) {
            if (this.selections[rule.id]) return false;
            const others = this.selectedRules;
            if (others.length === 0) return false;
            return !rule.stackable || others.some((other) => !other.stackable);
        },

        belowMinBill(rule) {
            return rule.minBill !== null && this.order && this.order.subtotal < rule.minBill;
        },

        toggleRule(rule) {
            if (this.selections[rule.id]) {
                delete this.selections[rule.id];
                this.selections = { ...this.selections };
                return;
            }
            if (this.isDisabled(rule) || this.belowMinBill(rule)) return;
            this.selections = {
                ...this.selections,
                [rule.id]: { value: '', eligMode: 'items', itemIds: [], eligibleAmount: '', qualifiedName: '' },
            };
        },

        round2(value) {
            return Math.round((value + Number.EPSILON) * 100) / 100;
        },

        basis(rule) {
            const pick = this.selections[rule.id];
            if (rule.scope !== 'eligible_items') return this.order.subtotal;
            if (pick.eligMode === 'items') {
                return this.round2(this.order.items.filter((item) => pick.itemIds.includes(item.id)).reduce((sum, item) => sum + item.amount, 0));
            }
            return Math.min(Number(pick.eligibleAmount) || 0, this.order.subtotal);
        },

        rateOf(rule) {
            return rule.isCustom ? Number(this.selections[rule.id].value) || 0 : Number(rule.value);
        },

        get preview() {
            if (!this.order) return { lines: [], total: 0 };
            let taken = 0;
            const lines = this.selectedRules.map((rule) => {
                const basis = this.basis(rule);
                const rate = this.rateOf(rule);
                let amount = rule.mode === 'fixed' ? Math.min(rate, basis) : this.round2((basis * rate) / 100);
                if (rule.maxAmount !== null) amount = Math.min(amount, rule.maxAmount);
                amount = Math.min(amount, this.round2(this.order.subtotal - taken));
                taken = this.round2(taken + amount);
                return { name: rule.name, rate: rule.mode === 'percent' ? rate : null, amount };
            });
            return { lines, total: this.round2(this.order.subtotal - taken) };
        },

        money(value) {
            return '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        payload() {
            return this.selectedRules.map((rule) => {
                const pick = this.selections[rule.id];
                const row = { rule_id: rule.id };
                if (rule.isCustom) row.value = pick.value;
                if (rule.scope === 'eligible_items') {
                    if (pick.eligMode === 'items') row.item_ids = pick.itemIds;
                    else row.eligible_amount = pick.eligibleAmount;
                }
                if (rule.askName && pick.qualifiedName.trim()) row.qualified_name = pick.qualifiedName.trim();
                return row;
            });
        },

        async save(discounts = null) {
            if (this.submitting) return;
            this.submitting = true;
            this.error = '';

            try {
                const response = await fetch(this.order.url, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ discounts: discounts ?? this.payload() }),
                });

                if (response.ok) {
                    reloadBoardNow();
                    return;
                }

                const body = await response.json().catch(() => ({}));
                const firstError = body.errors ? Object.values(body.errors).flat()[0] : null;
                this.error = firstError || body.message || this.text.failed;
            } catch (e) {
                this.error = this.text.failed;
            } finally {
                this.submitting = false;
            }
        },

        removeAll() {
            this.save([]);
        },
    };
}
