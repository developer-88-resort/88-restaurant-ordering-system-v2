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
                className="block mt-1 w-full max-w-[12rem] tracking-[0.4em] border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm"
            />
            {errors[id] && <p className="text-sm text-red-600 mt-2">{errors[id]}</p>}
        </div>
    );

    return (
        <section className="bg-white border border-[#E5DDD0] rounded-xl p-6">
            <h3 className="text-base font-semibold text-gray-900">{t('Change PIN')}</h3>
            <p className="text-sm text-gray-500 mt-1">
                {t('The PIN you enter after tapping your name on the sign-in screen. 4 to 6 digits, and not an easy one like 1234.')}
            </p>

            <form onSubmit={updatePin} className="mt-6 space-y-5">
                {pinInput('current_pin', t('Current PIN'), 'current-password')}
                {pinInput('pin', t('New PIN'), 'new-password')}
                {pinInput('pin_confirmation', t('Confirm New PIN'), 'new-password')}

                <div className="flex items-center gap-4">
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center px-4 py-2 bg-[#8A3330] border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-[#742927] transition ease-in-out duration-150 disabled:opacity-70"
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
