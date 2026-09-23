// The Kitchen Display reloads itself on every KitchenUpdated broadcast. With
// several tablets on the same board, another cook's action would wipe a
// cancel dialog someone is halfway through — so while a dialog is open the
// reload is held, and runs the moment the dialog closes.
let holdReload = false;
let reloadPending = false;

export function kitchenBoardUpdated() {
    if (holdReload) {
        reloadPending = true;
        return;
    }
    window.location.reload();
}

function releaseReload() {
    holdReload = false;
    if (reloadPending) {
        reloadPending = false;
        window.location.reload();
    }
}

// Shared with the board's other dialogs (kitchen-slip-discount.js).
export function holdBoardReload() {
    holdReload = true;
}

export function releaseBoardReload() {
    releaseReload();
}

export function reloadBoardNow() {
    reloadPending = false;
    holdReload = false;
    window.location.reload();
}

// Registered as Alpine.data('kitchenCancelDialog', ...) in app.js and used as
// x-data="kitchenCancelDialog(@js($config))" in
// kitchen/partials/cancel-item-dialog.blade.php. Each line's Cancel/Adjust
// button dispatches a `kitchen-cancel-item` window event carrying that line.
export function kitchenCancelDialog(config) {
    return {
        reasons: config.reasons,
        otherValue: config.otherValue,
        text: config.text,
        // Managers a staff member can pick to approve with their PIN; the
        // email + password fields stay one tap away (and are the only way
        // when no manager has a PIN yet).
        approvers: config.approvers ?? [],

        isOpen: false,
        step: 'form',
        item: null,
        keepQty: 0,
        reason: '',
        notes: '',
        approvalMode: 'pin',
        managerId: '',
        managerPin: '',
        managerEmail: '',
        managerPassword: '',
        submitting: false,
        error: '',

        open(item) {
            this.item = item;
            // "Adjust" starts one below what's on the slip (3 → 2); "Cancel"
            // starts at nothing kept. Both land on the same form.
            this.keepQty = item.mode === 'adjust' ? Math.max(0, item.activeQty - 1) : 0;
            this.reason = '';
            this.notes = '';
            this.approvalMode = this.approvers.length ? 'pin' : 'email';
            this.managerId = this.approvers.length === 1 ? String(this.approvers[0].id) : '';
            this.managerPin = '';
            this.managerEmail = '';
            this.managerPassword = '';
            this.error = '';
            this.step = 'form';
            this.submitting = false;
            this.isOpen = true;
            holdReload = true;
        },

        close() {
            this.isOpen = false;
            releaseReload();
        },

        get cancelQty() {
            return this.item ? this.item.activeQty - this.keepQty : 0;
        },

        get canAdjust() {
            return this.item && ! this.item.isWeighed && this.item.activeQty > 1;
        },

        get reasonLabel() {
            const match = this.reasons.find((r) => r.value === this.reason);
            return match ? match.label : '';
        },

        decrementKeep() {
            if (this.keepQty > 0) this.keepQty--;
        },

        incrementKeep() {
            if (this.item && this.keepQty < this.item.activeQty - 1) this.keepQty++;
        },

        review() {
            this.error = '';
            if (this.cancelQty < 1) {
                this.error = this.text.nothingToCancel;
                return;
            }
            if (! this.reason) {
                this.error = this.text.pickReason;
                return;
            }
            if (this.reason === this.otherValue && ! this.notes.trim()) {
                this.error = this.text.describeOther;
                return;
            }
            if (this.item.needsApproval && this.approvalMode === 'pin' && (! this.managerId || ! this.managerPin)) {
                this.error = this.text.approvalMissingPin;
                return;
            }
            if (this.item.needsApproval && this.approvalMode === 'email' && (! this.managerEmail || ! this.managerPassword)) {
                this.error = this.text.approvalMissing;
                return;
            }
            this.step = 'confirm';
        },

        approvalFields() {
            if (! this.item.needsApproval) return {};
            return this.approvalMode === 'pin'
                ? { manager_id: this.managerId, manager_pin: this.managerPin }
                : { manager_email: this.managerEmail, manager_password: this.managerPassword };
        },

        async submit() {
            if (this.submitting) return;
            this.submitting = true;
            this.error = '';

            try {
                const response = await fetch(this.item.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        quantity: this.cancelQty,
                        reason_code: this.reason,
                        notes: this.notes.trim() || null,
                        ...this.approvalFields(),
                    }),
                });

                if (response.ok) {
                    reloadPending = false;
                    holdReload = false;
                    window.location.reload();
                    return;
                }

                const body = await response.json().catch(() => ({}));
                const firstError = body.errors ? Object.values(body.errors).flat()[0] : null;
                this.error = firstError || body.message || this.text.failed;
                this.step = 'form';
            } catch (e) {
                this.error = this.text.failed;
                this.step = 'form';
            } finally {
                this.submitting = false;
            }
        },
    };
}
