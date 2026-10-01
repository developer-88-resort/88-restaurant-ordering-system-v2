import { useTranslation } from '@/lib/i18n';
import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';

/**
 * Staff/Admin: change the PIN used with your name on the sign-in screen.
 */
export default function ChangePinForm() {
    const t = useTranslation();

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_pin: '',
        pin: '',
        pin_confirmation: '',
    });

    const updatePin = (e) => {
        e.preventDefault();

        put(route('profile.pin.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.current_pin) reset('current_pin');
                if (errors.pin) reset('pin', 'pin_confirmation');
            },
        });
    };

    const pinInput = (id, label, autoComplete) => (
        <div>
            <label htmlFor={id} className="block text-sm font-medium text-gray-700">{label}</label>
            <input
                id={id}
                type="password"
                inputMode="numeric"
                pattern="[0-9]*"
                maxLength={6}
                value={data[id]}
                onChange={(e) => setData(id, e.target.value.replace(/\D/g, ''))}
                autoComplete={autoComplete}
                className="mt-2 block h-12 w-full max-w-[12rem] rounded-xl border-slate-200 bg-slate-50/50 px-4 text-sm tracking-[0.4em] text-slate-900 shadow-none focus:border-slate-400 focus:bg-white focus:ring-2 focus:ring-slate-200"
            />
            {errors[id] && <p className="text-sm text-red-600 mt-2">{errors[id]}</p>}
        </div>
    );

    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 className="text-base font-semibold tracking-tight text-slate-900">{t('Change PIN')}</h3>
            <p className="text-sm leading-6 text-slate-500 mt-1">
                {t('The PIN you enter after tapping your name on the sign-in screen. 4 to 6 digits, and not an easy one like 1234.')}
            </p>

            <form onSubmit={updatePin} className="mt-6 space-y-5">
                {pinInput('current_pin', t('Current PIN'), 'current-password')}
                {pinInput('pin', t('New PIN'), 'new-password')}
                {pinInput('pin_confirmation', t('Confirm New PIN'), 'new-password')}

                <div className="flex flex-wrap items-center justify-end gap-4 border-t border-slate-100 pt-5">
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex min-h-11 min-w-24 items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {t('Save')}
                    </button>
                    <Transition show={recentlySuccessful} enter="transition ease-in-out" enterFrom="opacity-0" leave="transition ease-in-out" leaveTo="opacity-0">
                        <p className="text-sm text-gray-500">{t('Saved.')}</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
