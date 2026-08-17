function emptyPaymentRow() {
    return {
        method: 'cash',
        amount: '',
        tendered: '',
        cardBrand: '',
        cardLastFour: '',
        terminalReference: '',
        approvalCode: '',
        terminalId: '',
        reference: '',
        notes: '',
    };
}

// Registered as Alpine.data('orderPayment', ...) in app.js and used as
// x-data="orderPayment(@js($config))" on the checkout form. Living here as
// a real JS module (not an inline x-data string in the Blade attribute)
// means comments, quotes, and multi-line logic can never truncate the
// attribute the way a stray " in an inline // comment once did.
export function orderPayment(config) {
    return {
        open: false,
        orderTotal: config.orderTotal,
        isVat: config.isVat,
        taxRate: config.taxRate,
        serviceChargeEnabled: config.serviceChargeEnabled,
        serviceChargePercent: config.serviceChargePercent,
        isStaff: config.isStaff,
        rules: config.rules,
        orderItems: config.orderItems,
        methods: config.methods,
        selections: {},
        overrideMode: false,
        managerEmail: '',
        managerPassword: '',
        showBuyerInfo: false,
        buyerName: '',
        buyerTin: '',
        buyerAddress: '',
        payments: [emptyPaymentRow()],

        toggleRule(rule) {
            if (this.selections[rule.id]) {
                delete this.selections[rule.id];
                return;
            }
            if (this.isDisabled(rule)) return;
            this.selections[rule.id] = {
                enteredValue: rule.isCustom ? '' : rule.value,
                qualifiedName: '',
                idNumber: '',
                reason: '',
                eligMode: 'items',
                itemIds: [],
                eligibleAmount: '',
            };
        },

        get selectedRules() {
            return this.rules.filter((r) => this.selections[r.id]).sort((a, b) => a.priority - b.priority);
        },

        get anySelectedExclusive() {
            return this.selectedRules.some((r) => !r.stackable);
        },

        isDisabled(rule) {
            if (this.selections[rule.id]) return false;
            if (this.selectedRules.length === 0) return false;
            if (this.overrideMode) return false;
            return !rule.stackable || this.anySelectedExclusive;
        },

        belowMinBill(rule) {
            return rule.minBill !== null && this.orderTotal < rule.minBill;
        },

        get needsApproval() {
            return this.selectedRules.some((r) => r.requiresApproval)
                || (this.selectedRules.length > 1 && this.anySelectedExclusive);
        },

        eligibleBase(rule) {
            const sel = this.selections[rule.id];
            if (!sel) return 0;
            if (rule.scope === 'eligible_items') {
                if (sel.eligMode === 'amount') return Math.min(Number(sel.eligibleAmount) || 0, this.orderTotal);
                return this.orderItems.filter((i) => sel.itemIds.includes(i.id)).reduce((sum, i) => sum + i.amount, 0);
            }
            return null;
        },

        // Which items (or typed amount) a scoped rule covers, and their
        // gross total — so the preview shows what's in scope, not just the
        // resulting discount number.
        eligibleScopeInfo(rule) {
            const sel = this.selections[rule.id];
            if (!sel || rule.scope !== 'eligible_items') {
                return { eligibleNames: null, eligibleGross: null };
            }
            if (sel.eligMode === 'amount') {
                return { eligibleNames: null, eligibleGross: Math.min(Number(sel.eligibleAmount) || 0, this.orderTotal) };
            }
            const covered = this.orderItems.filter((i) => sel.itemIds.includes(i.id));
            return {
                eligibleNames: covered.map((i) => i.name),
                eligibleGross: covered.reduce((sum, i) => sum + i.amount, 0),
            };
        },

        get discountPreview() {
            const lines = [];
            let statutoryEligible = 0;
            let statutoryDue = 0;
            for (const rule of this.selectedRules.filter((r) => r.statutory)) {
                const base = this.eligibleBase(rule) ?? this.orderTotal;
                statutoryEligible += base;
                // Senior/PWD: VAT is stripped from the eligible base BEFORE
                // the 20% applies (RA 9994 / RA 10754) — net is the taxable
                // base the discount actually applies to, vatExemption is
                // the VAT portion that base never had to begin with. Both
                // get their own display line so nothing is subtracted
                // silently; the total itself is unchanged either way.
                const net = this.isVat ? base / (1 + this.taxRate / 100) : base;
                const vatExemption = this.isVat ? base - net : 0;
                const pct = Number(this.selections[rule.id].enteredValue ?? rule.value ?? 20);
                let disc = net * pct / 100;
                if (rule.maxDiscount !== null) disc = Math.min(disc, rule.maxDiscount);
                statutoryDue += net - disc;

                if (vatExemption > 0.004) {
                    lines.push({ kind: 'vatExemption', amount: vatExemption });
                }
                lines.push({
                    kind: 'discount',
                    ruleName: rule.name,
                    amount: disc,
                    pct,
                    basisNet: this.isVat ? net : null,
                    ...this.eligibleScopeInfo(rule),
                });
            }
            let runningDue = Math.max(0, this.orderTotal - statutoryEligible);
            for (const rule of this.selectedRules.filter((r) => !r.statutory && r.mode === 'percent')) {
                const explicitBase = this.eligibleBase(rule);
                const base = Math.min(explicitBase ?? runningDue, runningDue);
                const pct = Number(this.selections[rule.id].enteredValue ?? rule.value ?? 0);
                let disc = base * pct / 100;
                if (rule.maxDiscount !== null) disc = Math.min(disc, rule.maxDiscount);
                disc = Math.min(disc, runningDue);
                runningDue -= disc;
                lines.push({
                    kind: 'discount',
                    ruleName: rule.name,
                    amount: disc,
                    pct,
                    basisNet: null,
                    ...this.eligibleScopeInfo(rule),
                });
            }
            for (const rule of this.selectedRules.filter((r) => !r.statutory && r.mode === 'fixed')) {
                let disc = Number(this.selections[rule.id].enteredValue ?? rule.value ?? 0);
                if (rule.maxDiscount !== null) disc = Math.min(disc, rule.maxDiscount);
                disc = Math.min(disc, runningDue);
                runningDue -= disc;
                lines.push({
                    kind: 'discount',
                    ruleName: rule.name,
                    amount: disc,
                    pct: null,
                    basisNet: null,
                    ...this.eligibleScopeInfo(rule),
                });
            }
            const serviceCharge = (this.serviceChargeEnabled && this.serviceChargePercent > 0)
                ? this.orderTotal * this.serviceChargePercent / 100
                : 0;
            return { lines, serviceCharge, totalDue: Math.max(0, statutoryDue + runningDue + serviceCharge) };
        },

        get estimatedTotalDue() {
            return this.discountPreview.totalDue;
        },

        addPaymentRow() {
            this.payments.push(emptyPaymentRow());
        },

        removePaymentRow(index) {
            this.payments.splice(index, 1);
            if (this.payments.length === 0) this.addPaymentRow();
        },

        // What this row can still apply toward the bill: total due minus
        // every OTHER row's current amount. Amount Applied is always
        // clamped to this, which is what makes overpayment become change
        // instead of a negative balance.
        remainingForRow(index) {
            const others = this.payments.reduce((sum, row, i) => (i === index ? sum : sum + (Number(row.amount) || 0)), 0);
            return Math.max(0, this.estimatedTotalDue - others);
        },

        fillRemaining(index) {
            this.payments[index].amount = this.remainingForRow(index).toFixed(2);
        },

        // Cash Tendered is the cashier's real cash in hand, never capped.
        // Amount Applied auto-derives from it: min(tendered, whatever this
        // row can still cover).
        onTenderedInput(index) {
            const row = this.payments[index];
            const remaining = this.remainingForRow(index);
            const tendered = Math.max(0, Number(row.tendered) || 0);
            row.amount = Math.min(tendered, remaining).toFixed(2);
        },

        // Amount Applied stays manually overridable for split payments,
        // but a cash row can never carry more than what's still due. If
        // the cashier types the cash they received in here instead of
        // Cash Tendered, treat it as tendered and re-derive rather than
        // letting it push the balance negative.
        onAmountInput(index) {
            const row = this.payments[index];
            const remaining = this.remainingForRow(index);
            const entered = Math.max(0, Number(row.amount) || 0);
            if (row.method === 'cash' && entered > remaining + 0.004) {
                row.tendered = row.amount;
            }
            row.amount = Math.min(entered, remaining).toFixed(2);
        },

        get totalPaid() {
            return this.payments.reduce((sum, row) => sum + (Number(row.amount) || 0), 0);
        },

        get remainingBalance() {
            return Math.max(0, this.estimatedTotalDue - this.totalPaid);
        },

        get totalChange() {
            return this.payments.reduce((sum, row) => {
                if (row.method !== 'cash') return sum;
                const tendered = Number(row.tendered) || Number(row.amount) || 0;
                return sum + Math.max(0, tendered - (Number(row.amount) || 0));
            }, 0);
        },

        get insufficientAmount() {
            return this.remainingBalance > 0.004;
        },

        methodInfo(value) {
            return this.methods.find((m) => m.value === value) ?? { requiresReference: false };
        },
    };
}
