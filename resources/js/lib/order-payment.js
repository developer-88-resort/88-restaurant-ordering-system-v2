// Half-up to 2 decimals, the way the server rounds money. The epsilon keeps
// a value like 1.005 (stored as 1.00499…) from rounding the wrong way.
function roundCentavos(value) {
    return Math.round((value + Number.EPSILON) * 100) / 100;
}

function emptyPaymentRow() {
    return {
        method: 'cash',
        amount: '',
        tendered: '',
        cardBrand: '',
        // Foreign card (Korean, …): a surcharge on top of what it covers.
        foreignCard: false,
        settledVia: '',
        chargedTo: '',
        cardLastFour: '',
        terminalReference: '',
        approvalCode: '',
        terminalId: '',
        reference: '',
        notes: '',
        // Room Charge: a room picked from the grid, plus who signed for it.
        roomId: '',
        roomLabel: '',
        roomTypeName: '',
        guestName: '',
        guestRef: '',
        guestSuggested: false,
        pickerOpen: true,
        // Locker / Kubo Charge: the number picked, and for which of the two
        // (switching the method clears it — Locker 12 is not Kubo 12).
        spotNo: '',
        spotMethod: '',
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
        cardBrands: config.cardBrands ?? [],
        foreignCardSurchargePercent: Number(config.foreignCardSurchargePercent ?? 12),
        settlementMethods: config.settlementMethods ?? [],
        // Room picker (App\Support\RoomChargePicker): active rooms grouped
        // by type, room charges per room today, guest last used per room.
        roomGroups: config.rooms?.groups ?? [],
        chargedToday: config.rooms?.chargedToday ?? {},
        lastGuest: config.rooms?.lastGuest ?? {},
        // Locker / Kubo Charge (RoomChargePicker::spots): per method, the
        // numbers in groups of ten, charges today and last guest per number.
        spots: config.rooms?.spots ?? {},
        // Correcting a paid bill (checkout-form's $lateDiscount mode): the
        // bill's current discounts start ticked, and what was paid is the
        // figure the new total is compared against.
        lateDiscount: !!config.lateDiscount,
        paidTotal: config.paidTotal ?? 0,
        selections: { ...(config.initialSelections ?? {}) },
        overrideMode: false,
        managerEmail: '',
        managerPassword: '',
        showBuyerInfo: false,
        buyerName: '',
        buyerTin: '',
        buyerAddress: '',
        payments: [emptyPaymentRow()],
        // A breakfast order (App\Support\Breakfast): Others only, always for
        // the whole amount due — see init().
        complimentary: !!config.complimentary,

        init() {
            if (!this.complimentary) return;
            const sync = () => {
                this.payments = this.payments.slice(0, 1);
                this.payments[0].method = 'other';
                this.payments[0].amount = this.estimatedTotalDue.toFixed(2);
            };
            sync();
            this.$watch('estimatedTotalDue', sync);
        },

        // A discount that names a person (Senior, PWD, Diplomat) can be
        // claimed by several people on one bill — each with their own name,
        // ID and, for Senior/PWD, their own food. Every person becomes one
        // discounts[] row and one line on the invoice.
        newPerson() {
            return { qualifiedName: '', idNumber: '', eligMode: 'items', itemIds: [], eligibleAmount: '' };
        },

        toggleRule(rule) {
            if (this.selections[rule.id]) {
                delete this.selections[rule.id];
                return;
            }
            if (this.isDisabled(rule)) return;
            this.selections[rule.id] = {
                enteredValue: rule.isCustom ? '' : rule.value,
                reason: '',
                // 'per_person' rules (Diplomat): the group's headcount starts
                // at the order's pax when one was recorded.
                totalPersons: config.orderPax || '',
                persons: [this.newPerson()],
            };
        },

        addPerson(rule) {
            this.selections[rule.id].persons.push(this.newPerson());
        },

        removePerson(rule, pIndex) {
            const persons = this.selections[rule.id].persons;
            persons.splice(pIndex, 1);
            if (persons.length === 0) delete this.selections[rule.id];
        },

        // One diner's equal share of the bill (Diplomat) — mirrors
        // CheckoutDiscountResolver::shareOf, rounded to the centavo.
        perPersonShare(rule) {
            const persons = parseInt(this.selections[rule.id]?.totalPersons, 10) || 0;
            if (persons < 1) return 0;
            return Math.round((this.orderTotal / persons) * 100) / 100;
        },

        // More diplomats keyed than diners in the group — the server refuses it.
        tooManyPersons(rule) {
            const sel = this.selections[rule.id];
            if (!sel || rule.scope !== 'per_person') return false;
            const persons = parseInt(sel.totalPersons, 10) || 0;
            return persons > 0 && sel.persons.length > persons;
        },

        // An item already covered by another Senior/PWD on this bill can not
        // be claimed twice — it would take the VAT off the same food twice.
        itemClaimedElsewhere(itemId, rule, pIndex) {
            return this.discountRows.some((row) => row.rule.statutory
                && row.rule.scope === 'eligible_items'
                && !(row.rule.id === rule.id && row.pIndex === pIndex)
                && row.person.eligMode === 'items'
                && row.person.itemIds.includes(itemId));
        },

        get selectedRules() {
            return this.rules.filter((r) => this.selections[r.id]).sort((a, b) => a.priority - b.priority);
        },

        // Every discounts[] row to send: one per person on a person rule,
        // one for any other rule.
        get discountRows() {
            return this.selectedRules.flatMap((rule) => {
                const sel = this.selections[rule.id];
                const persons = rule.requiresId ? sel.persons : sel.persons.slice(0, 1);
                return persons.map((person, pIndex) => ({ rule, sel, person, pIndex }));
            });
        },

        // The same limits the server enforces (CheckoutDiscountResolver),
        // shown before submitting instead of as an error after: no person's
        // eligible amount above the bill, and every Senior / PWD / Diplomat
        // base together within the bill (they all come out of the same food).
        get statutoryProblem() {
            let combined = 0;
            for (const { rule, person } of this.discountRows.filter((row) => row.rule.statutory)) {
                let base;
                if (rule.scope === 'per_person') {
                    base = this.perPersonShare(rule);
                } else if (person.eligMode === 'amount') {
                    base = Number(person.eligibleAmount) || 0;
                } else {
                    base = this.orderItems.filter((i) => person.itemIds.includes(i.id)).reduce((sum, i) => sum + i.amount, 0);
                }
                if (base > this.orderTotal + 0.004) {
                    return `The eligible amount for ${rule.name} cannot exceed the order total (₱${this.orderTotal.toFixed(2)}).`;
                }
                combined += base;
            }
            if (combined > this.orderTotal + 0.004) {
                return `Senior / PWD / Diplomat together cover ₱${combined.toFixed(2)}, more than the bill (₱${this.orderTotal.toFixed(2)}) — each peso can only be discounted once.`;
            }
            return null;
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
            // Changing a bill that's already paid always needs a manager.
            if (this.lateDiscount) return true;
            return this.selectedRules.some((r) => r.requiresApproval)
                || (this.selectedRules.length > 1 && this.anySelectedExclusive);
        },

        eligibleBase(rule, person) {
            if (rule.scope === 'per_person') return this.perPersonShare(rule);
            if (rule.scope === 'eligible_items') {
                if (person.eligMode === 'amount') return Math.min(Number(person.eligibleAmount) || 0, this.orderTotal);
                return this.orderItems.filter((i) => person.itemIds.includes(i.id)).reduce((sum, i) => sum + i.amount, 0);
            }
            return null;
        },

        // Who and what a line covers — so the preview shows the person and
        // the food in scope, not just the resulting number.
        eligibleScopeInfo(rule, person) {
            const who = rule.requiresId ? (person.qualifiedName || null) : null;
            if (rule.scope === 'per_person') {
                const persons = parseInt(this.selections[rule.id].totalPersons, 10) || 0;
                return { who, eligibleNames: null, eligibleGross: this.perPersonShare(rule), personsNote: persons > 0 ? `1 / ${persons}` : null };
            }
            if (rule.scope !== 'eligible_items') return { who, eligibleNames: null, eligibleGross: null };
            if (person.eligMode === 'amount') {
                return { who, eligibleNames: null, eligibleGross: Math.min(Number(person.eligibleAmount) || 0, this.orderTotal) };
            }
            const covered = this.orderItems.filter((i) => person.itemIds.includes(i.id));
            return { who, eligibleNames: covered.map((i) => i.name), eligibleGross: covered.reduce((sum, i) => sum + i.amount, 0) };
        },

        get discountPreview() {
            const lines = [];
            let statutoryEligible = 0;
            let statutoryDue = 0;
            for (const { rule, sel, person } of this.discountRows.filter((row) => row.rule.statutory)) {
                const base = this.eligibleBase(rule, person) ?? this.orderTotal;
                statutoryEligible += base;
                // Senior/PWD: VAT is stripped from the eligible base BEFORE
                // the 20% applies (RA 9994 / RA 10754) — net is the taxable
                // base the discount actually applies to, vatExemption is
                // the VAT portion that base never had to begin with. Both
                // get their own display line so nothing is subtracted
                // silently; the total itself is unchanged either way.
                const net = this.isVat ? base / (1 + this.taxRate / 100) : base;
                const vatExemption = this.isVat ? base - net : 0;
                const pct = Number(sel.enteredValue ?? rule.value ?? 20);
                let disc = net * pct / 100;
                if (rule.maxDiscount !== null) disc = Math.min(disc, rule.maxDiscount);
                statutoryDue += net - disc;

                if (pct === 0) {
                    // VAT-exempt only (Diplomat): the exemption IS the whole
                    // effect, so it carries the rule's name and scope instead
                    // of a "−₱0.00" discount line beside it.
                    lines.push({ kind: 'vatExemption', amount: vatExemption, ruleName: rule.name, ...this.eligibleScopeInfo(rule, person) });
                    continue;
                }
                if (vatExemption > 0.004) {
                    lines.push({ kind: 'vatExemption', amount: vatExemption });
                }
                lines.push({
                    kind: 'discount',
                    ruleName: rule.name,
                    amount: disc,
                    pct,
                    basisNet: this.isVat ? net : null,
                    ...this.eligibleScopeInfo(rule, person),
                });
            }
            let runningDue = Math.max(0, this.orderTotal - statutoryEligible);
            for (const { rule, sel, person } of this.discountRows.filter((row) => !row.rule.statutory && row.rule.mode === 'percent')) {
                const explicitBase = this.eligibleBase(rule, person);
                const base = Math.min(explicitBase ?? runningDue, runningDue);
                const pct = Number(sel.enteredValue ?? rule.value ?? 0);
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
                    ...this.eligibleScopeInfo(rule, person),
                });
            }
            for (const { rule, sel, person } of this.discountRows.filter((row) => !row.rule.statutory && row.rule.mode === 'fixed')) {
                let disc = Number(sel.enteredValue ?? rule.value ?? 0);
                if (rule.maxDiscount !== null) disc = Math.min(disc, rule.maxDiscount);
                disc = Math.min(disc, runningDue);
                runningDue -= disc;
                lines.push({
                    kind: 'discount',
                    ruleName: rule.name,
                    amount: disc,
                    pct: null,
                    basisNet: null,
                    ...this.eligibleScopeInfo(rule, person),
                });
            }
            const serviceCharge = (this.serviceChargeEnabled && this.serviceChargePercent > 0)
                ? this.orderTotal * this.serviceChargePercent / 100
                : 0;
            // Rounded to the centavo exactly as the server rounds what is due
            // (InvoiceCalculator::round2, half-up). Left unrounded, a VAT-exempt
            // discount leaves e.g. ₱1,935.7142857 here against ₱1,935.71 paid,
            // and that fraction of a centavo blocked Confirm Payment while the
            // screen showed "Remaining Balance ₱0.00".
            return { lines, serviceCharge, totalDue: roundCentavos(Math.max(0, statutoryDue + runningDue + serviceCharge)) };
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
            if (this.paidAs(row) === 'cash' && entered > remaining + 0.004) {
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
                if (this.paidAs(row) !== 'cash') return sum;
                const tendered = Number(row.tendered) || Number(row.amount) || 0;
                return sum + Math.max(0, tendered - (Number(row.amount) || 0));
            }, 0);
        },

        get insufficientAmount() {
            // A paid bill being corrected keeps its recorded payments.
            if (this.lateDiscount) return false;
            return this.remainingBalance > 0.004;
        },

        get lateDifference() {
            return Math.max(0, this.paidTotal - this.estimatedTotalDue);
        },

        // Which method's own fields a row asks for: a Room Charge takes those
        // of the mode it's paid through, as if that mode were picked directly.
        paidAs(row) {
            return row.method === 'room_charge' ? (row.settledVia || 'room_charge') : row.method;
        },

        // "51" → 510–517; "PH" → every Pension House room.
        filteredRoomGroups(query) {
            const q = String(query ?? '').trim().toLowerCase();
            if (q === '') return this.roomGroups;
            return this.roomGroups
                .map((group) => ({
                    ...group,
                    rooms: group.rooms.filter((room) => room.no.toLowerCase().startsWith(q)
                        || room.label.toLowerCase().includes(q)
                        || group.name.toLowerCase().includes(q)),
                }))
                .filter((group) => group.rooms.length > 0);
        },

        pickRoom(row, room) {
            const changed = row.roomId !== room.id;
            row.roomId = room.id;
            row.roomLabel = room.label;
            row.roomTypeName = room.typeName;
            row.pickerOpen = false;
            // Offer the name last charged to this room (past 24h), never
            // over something the cashier already typed.
            if (changed && (row.guestName === '' || row.guestSuggested)) {
                const suggestion = this.lastGuest[room.id] ?? '';
                row.guestName = suggestion;
                row.guestSuggested = suggestion !== '';
            }
        },

        // Type a room number + Enter: an exact number wins, else the only match.
        pickTypedRoom(row, query) {
            const q = String(query ?? '').trim().toLowerCase();
            if (q === '') return;
            const rooms = this.filteredRoomGroups(q).flatMap((group) => group.rooms);
            const room = rooms.find((r) => r.no.toLowerCase() === q) ?? (rooms.length === 1 ? rooms[0] : null);
            if (room) this.pickRoom(row, room);
        },

        get roomChargeRows() {
            return this.payments.filter((row) => row.method === 'room_charge');
        },

        // Locker Charge / Kubo Charge.
        isSpotMethod(method) {
            return Object.prototype.hasOwnProperty.call(this.spots, method);
        },

        spotInfo(method) {
            return this.spots[method] ?? { name: '', count: 0, groups: [], chargedToday: {}, lastGuest: {} };
        },

        // The number picked for the row's current method, or ''.
        pickedSpot(row) {
            return row.spotMethod === row.method ? row.spotNo : '';
        },

        spotLabel(row) {
            const no = this.pickedSpot(row);
            return no ? this.spotInfo(row.method).name + ' ' + no : '';
        },

        spotChargedToday(row, no) {
            return this.spotInfo(row.method).chargedToday[no] ?? 0;
        },

        // "1" → 1, 10–19; groups with nothing left are dropped.
        filteredSpotGroups(method, query) {
            const q = String(query ?? '').trim();
            const groups = this.spotInfo(method).groups;
            if (q === '') return groups;
            return groups
                .map((group) => ({ ...group, nos: group.nos.filter((no) => String(no).startsWith(q)) }))
                .filter((group) => group.nos.length > 0);
        },

        pickSpot(row, no) {
            const changed = this.pickedSpot(row) !== no;
            row.spotNo = no;
            row.spotMethod = row.method;
            row.pickerOpen = false;
            if (changed && (row.guestName === '' || row.guestSuggested)) {
                const suggestion = this.spotInfo(row.method).lastGuest[no] ?? '';
                row.guestName = suggestion;
                row.guestSuggested = suggestion !== '';
            }
        },

        // Type a number + Enter.
        pickTypedSpot(row, query) {
            const no = parseInt(String(query ?? '').trim(), 10);
            if (no >= 1 && no <= this.spotInfo(row.method).count) this.pickSpot(row, no);
        },

        // Every room, locker and kubo charge, spelled out in the confirm dialog.
        get chargeConfirmations() {
            return this.payments
                .filter((row) => row.method === 'room_charge' || this.isSpotMethod(row.method))
                .map((row) => ({
                    amount: Number(row.amount) || 0,
                    target: row.method === 'room_charge'
                        ? 'ROOM ' + (row.roomLabel || '?') + (row.roomTypeName ? ' (' + row.roomTypeName + ')' : '')
                        : (this.spotLabel(row) || this.spotInfo(row.method).name + ' ?').toUpperCase(),
                    guestName: row.guestName,
                }));
        },

        settlementLabel(value) {
            return this.settlementMethods.find((m) => m.value === value)?.label ?? value;
        },

        // What a foreign card pays on top of the part of the bill it covers —
        // mirrors PaymentFinalizer::surchargeOn (half-up to the centavo).
        surchargeFor(row) {
            if (this.paidAs(row) !== 'card' || !row.foreignCard) return 0;
            return roundCentavos((Number(row.amount) || 0) * this.foreignCardSurchargePercent / 100);
        },

        // What to key into the card terminal for this row.
        terminalAmount(row) {
            return roundCentavos((Number(row.amount) || 0) + this.surchargeFor(row));
        },

        get foreignCardRows() {
            return this.payments.filter((row) => this.surchargeFor(row) > 0);
        },

        cardBrandLabel(value) {
            return this.cardBrands.find((b) => b.value === value)?.label ?? value;
        },

        methodInfo(value) {
            return this.methods.find((m) => m.value === value) ?? { requiresReference: false };
        },
    };
}
