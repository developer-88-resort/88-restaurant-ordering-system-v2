import { ToastList } from '@/Components/Toast';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function PasswordToggleIcon({ shown }) {
    return shown ? (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
        </svg>
    ) : (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
    );
}

export default function AcceptInvitation({ token, email }) {
    const t = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [toasts, setToasts] = useState([]);

    useEffect(() => {
        const message = Object.values(errors)[0];
        if (message) setToasts([{ id: Date.now(), type: 'error', message }]);
    }, [errors]);

    const dismissToast = (id) => setToasts((current) => current.filter((toast) => toast.id !== id));

    const submit = (e) => {
        e.preventDefault();

        post(route('invitation.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Activate Your Account" />

            <div className="w-full max-w-sm">
                <div className="flex flex-col items-center mb-8">
                    <img src="/images/logo.png" alt="88 Hot Spring Resort" className="h-28 w-auto object-contain mb-4" />

                    <h1
                        className="text-[29px] font-medium text-center text-black leading-none"
                        style={{ fontFamily: "'Playfair Display', serif" }}
                    >
                        88 Hot Spring Resort
                    </h1>

                    <p className="text-[#6F6258] text-lg mt-1 font-normal">{t('Activate Your Account')}</p>
                </div>

                <p className="mb-4 text-sm text-[#6F6258] text-center">
                    {t('Welcome! Create a password to finish setting up your account.')}
                </p>

                <form onSubmit={submit}>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        readOnly
                        className="w-full mb-4 rounded-xl border border-[#D9CCBA] bg-gray-100 px-4 py-3 text-sm text-[#333] outline-none cursor-not-allowed"
                    />

                    <div className="relative mb-4">
                        <input
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            name="password"
                            placeholder={t('Password')}
                            autoComplete="new-password"
                            value={data.password}
                            required
                            autoFocus
                            onChange={(e) => setData('password', e.target.value)}
                            className="w-full rounded-xl border border-[#D9CCBA] bg-white px-4 py-3 pr-11 text-sm text-[#333] placeholder:text-gray-400 outline-none focus:border-[#8A3330] focus:ring-2 focus:ring-[#8A3330]"
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword((v) => !v)}
                            className="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-[#8A3330] transition-colors"
                            aria-label={showPassword ? t('Hide password') : t('Show password')}
                        >
                            <PasswordToggleIcon shown={showPassword} />
                        </button>
                    </div>

                    <div className="relative mb-4">
                        <input
                            id="password_confirmation"
                            type={showConfirmPassword ? 'text' : 'password'}
                            name="password_confirmation"
                            placeholder={t('Confirm Password')}
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            required
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            className="w-full rounded-xl border border-[#D9CCBA] bg-white px-4 py-3 pr-11 text-sm text-[#333] placeholder:text-gray-400 outline-none focus:border-[#8A3330] focus:ring-2 focus:ring-[#8A3330]"
                        />
                        <button
                            type="button"
                            onClick={() => setShowConfirmPassword((v) => !v)}
                            className="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-[#8A3330] transition-colors"
                            aria-label={showConfirmPassword ? t('Hide password') : t('Show password')}
                        >
                            <PasswordToggleIcon shown={showConfirmPassword} />
                        </button>
                    </div>

                    <ToastList toasts={toasts} onDismiss={dismissToast} />

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-xl bg-[#8A3330] hover:bg-[#742927] text-white font-semibold py-3 transition duration-200 disabled:opacity-70"
                    >
                        {t('Activate Account')}
                    </button>
                </form>
            </div>
        </GuestLayout>
    );
}
