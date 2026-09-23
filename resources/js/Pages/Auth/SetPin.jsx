import PinPad from '@/Components/PinPad';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Choosing your own PIN — the first sign-in since PIN sign-in arrived, or
 * right after signing in with a PIN an admin set. Enter it, then again to
 * confirm. Nothing else in the app opens until this is done.
 */
export default function SetPin({ replacingTemporaryPin, pinLength }) {
    const t = useTranslation();
    const { auth, csrf_token: csrfToken } = usePage().props;
    const [step, setStep] = useState('enter');
    const [mismatch, setMismatch] = useState(null);
    const { data, setData, post, processing, errors, clearErrors } = useForm({
        pin: '',
        pin_confirmation: '',
    });

    const field = step === 'enter' ? 'pin' : 'pin_confirmation';
    const error = step === 'enter' ? errors.pin : mismatch;

    const change = (value) => {
        clearErrors();
        setMismatch(null);
        setData(field, value);
    };

    const next = () => {
        if (step === 'enter') {
            setData('pin_confirmation', '');
            setStep('confirm');
            return;
        }

        if (data.pin_confirmation !== data.pin) {
            setMismatch(t('The two PINs don\'t match. Enter your new PIN again.'));
            setData({ pin: '', pin_confirmation: '' });
            setStep('enter');
            return;
        }

        post(route('pin.setup.store'), {
            onError: () => {
                setData({ pin: '', pin_confirmation: '' });
                setStep('enter');
            },
        });
    };

    return (
        <GuestLayout>
            <Head title={t('Set up your PIN')} />

            <div className="w-full max-w-sm py-10">
                <div className="rounded-3xl border border-[#E5DDD0] bg-white/70 p-5 shadow-[0_22px_60px_-48px_rgba(55,35,30,0.7)]">
                    <div className="text-center">
                        <p className="text-xs font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{auth.user.name}</p>
                        <h1 className="mt-1 text-xl font-bold text-[#251C19]">
                            {step === 'enter' ? t('Choose your PIN') : t('Enter it again to confirm')}
                        </h1>
                        <p className="mt-2 text-sm leading-6 text-[#6C5E57]">
                            {step === 'enter'
                                ? replacingTemporaryPin
                                    ? t('Replace the PIN you were given with one only you know.')
                                    : t('From now on you sign in by tapping your name and entering this PIN.')
                                : t('Type the same PIN once more.')}
                        </p>
                        {step === 'enter' && (
                            <p className="mt-2 text-xs text-[#8A7B6D]">
                                {t('4 to 6 digits. Avoid easy ones like 1234, 0000 or 1212.')}
                            </p>
                        )}
                    </div>

                    <div className="mt-5">
                        <PinPad
                            key={step}
                            value={data[field]}
                            onChange={change}
                            onSubmit={next}
                            length={pinLength}
                            disabled={processing}
                            error={error}
                            submitLabel={step === 'enter' ? t('Next') : t('Save PIN')}
                        />
                    </div>

                    {step === 'confirm' && (
                        <button
                            type="button"
                            onClick={() => {
                                setData({ pin: '', pin_confirmation: '' });
                                setStep('enter');
                            }}
                            className="mt-3 w-full text-center text-sm font-medium text-[#8A3330] hover:underline"
                        >
                            ← {t('Start over')}
                        </button>
                    )}
                </div>

                <form method="POST" action={route('logout')} data-turbo="false" className="mt-6 text-center">
                    <input type="hidden" name="_token" value={csrfToken} />
                    <button type="submit" className="text-sm font-medium text-[#8A7B6D] hover:text-[#302521] hover:underline">
                        {t('Not now — sign out')}
                    </button>
                </form>
            </div>
        </GuestLayout>
    );
}
