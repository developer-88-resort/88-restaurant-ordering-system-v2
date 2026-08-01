import { ToastList } from '@/Components/Toast';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function ForgotPassword({ status }) {
    const t = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });
    const [toasts, setToasts] = useState([]);

    useEffect(() => {
        const message = Object.values(errors)[0];
        if (message) setToasts([{ id: Date.now(), type: 'error', message }]);
    }, [errors]);

    const dismissToast = (id) => setToasts((current) => current.filter((toast) => toast.id !== id));

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Forgot Password" />

            <div className="w-full max-w-sm">
                <div className="flex flex-col items-center mb-8">
                    <img src="/images/logo.png" alt="88 Hot Spring Resort" className="h-28 w-auto object-contain mb-4" />

                    <h1
                        className="text-[29px] font-medium text-center text-black leading-none"
                        style={{ fontFamily: "'Playfair Display', serif" }}
                    >
                        88 Hot Spring Resort
                    </h1>

                    <p className="text-[#6F6258] text-lg mt-1 font-normal">{t('Reset Your Password')}</p>
                </div>

                <p className="mb-4 text-sm text-[#6F6258] text-center">
                    {t('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.')}
                </p>

                {status && (
                    <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3">
                        <p className="text-sm text-green-700">{status}</p>
                    </div>
                )}

                <form onSubmit={submit}>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        placeholder={t('Email')}
                        autoComplete="username"
                        value={data.email}
                        required
                        autoFocus
                        onChange={(e) => setData('email', e.target.value)}
                        className="w-full mb-4 rounded-xl border border-[#D9CCBA] bg-white px-4 py-3 text-sm text-[#333] placeholder:text-gray-400 outline-none focus:border-[#8A3330] focus:ring-2 focus:ring-[#8A3330]"
                    />

                    <ToastList toasts={toasts} onDismiss={dismissToast} />

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-xl bg-[#8A3330] hover:bg-[#742927] text-white font-semibold py-3 transition duration-200 disabled:opacity-70"
                    >
                        {t('Email Password Reset Link')}
                    </button>

                    <p className="mt-4 text-center text-sm">
                        <Link href={route('login')} className="text-[#8A3330] hover:underline font-medium">
                            {t('Back to Sign In')}
                        </Link>
                    </p>
                </form>
            </div>
        </GuestLayout>
    );
}
