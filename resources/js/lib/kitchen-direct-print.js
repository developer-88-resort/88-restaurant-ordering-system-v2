// Direct Print on the Kitchen Display: one press, one slip.
//
// The press is locked from the moment it is made until that slip is actually
// on paper (or the printer reported a failure), so an impatient second press
// can't put another copy on the queue. The server refuses duplicates too —
// pressing again while a slip is waiting hands back the same job — and the
// card is told about an already-waiting job when the board renders, so a
// board refresh mid-print doesn't unlock the button.

const POLL_EVERY_MS = 2000;
const FIRST_POLL_MS = 900;
// Long enough for a queued slip to reach a bridge that is between polls;
// after this the button unlocks so the kitchen isn't stuck, and pressing it
// again still can't duplicate the slip.
const GIVE_UP_AFTER_MS = 90000;
const RESET_AFTER_MS = 4000;

export function kitchenDirectPrint(config) {
    return {
        state: config.activeJobId ? 'printing' : 'idle',
        jobId: config.activeJobId ?? null,
        timer: null,

        init() {
            if (this.jobId) {
                this.watch();
            }
        },

        destroy() {
            clearTimeout(this.timer);
        },

        get busy() {
            return this.state === 'sending' || this.state === 'printing';
        },

        async send() {
            if (this.busy) return;

            this.state = 'sending';

            try {
                const response = await fetch(config.queueUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        Accept: 'application/json',
                    },
                });

                if (! response.ok) throw new Error('Could not queue the slip.');

                const body = await response.json();
                this.jobId = body.job_id ?? null;
                this.state = 'printing';
                this.watch();
            } catch (e) {
                this.finish('failed');
            }
        },

        watch() {
            const startedAt = Date.now();

            const check = async () => {
                if (! this.jobId) return;

                try {
                    const response = await fetch(config.statusUrl.replace('__JOB__', this.jobId), {
                        headers: { Accept: 'application/json' },
                    });
                    const body = await response.json();

                    if (body.status === 'printed' || body.status === 'failed') {
                        this.finish(body.status);

                        return;
                    }
                } catch (e) {
                    // A dropped poll means nothing about the slip itself —
                    // keep waiting rather than freeing the button early.
                }

                if (Date.now() - startedAt > GIVE_UP_AFTER_MS) {
                    this.finish('waiting');

                    return;
                }

                this.timer = setTimeout(check, POLL_EVERY_MS);
            };

            clearTimeout(this.timer);
            this.timer = setTimeout(check, FIRST_POLL_MS);
        },

        finish(state) {
            clearTimeout(this.timer);
            this.timer = null;
            this.jobId = null;
            this.state = state;
            setTimeout(() => {
                if (! this.busy) this.state = 'idle';
            }, RESET_AFTER_MS);
        },
    };
}
