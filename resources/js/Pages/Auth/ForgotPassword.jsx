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
        <GuestLayout modern>
            <Head title="Forgot Password" />

            <div className="w-full max-w-sm py-8 sm:py-12">
                <div className="mb-8 text-center">
                    <img src="/images/logo.png" alt="88 Hot Spring Resort" className="mx-auto mb-6 h-24 w-24 object-contain" />

                    <h1
                        className="text-[28px] font-semibold leading-tight tracking-tight text-slate-900"
                    >
                        88 Hot Spring Resort
                    </h1>

                    <p className="mt-2 text-base font-medium text-slate-500">{t('Reset Your Password')}</p>
                </div>

                <p className="mb-6 text-center text-sm leading-6 text-slate-500">
                    {t('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.')}
                </p>

                {status && (
                    <div role="status" className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                        <p className="text-sm text-green-700">{status}</p>
                    </div>
                )}

                <form onSubmit={submit}>
                    <label htmlFor="email" className="sr-only">{t('Email')}</label>
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
                        className="mb-4 min-h-12 w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-sm text-slate-900 placeholder:text-slate-400 shadow-[0_2px_4px_rgba(15,23,42,0.02)] outline-none transition-colors hover:border-slate-300 focus:border-slate-400 focus:ring-4 focus:ring-slate-200/50"
                    />

                    <ToastList toasts={toasts} onDismiss={dismissToast} />

                    <button
                        type="submit"
                        disabled={processing}
                        className="min-h-12 w-full rounded-xl bg-slate-800 px-4 py-3.5 text-sm font-semibold text-white shadow-[0_4px_10px_-4px_rgba(15,23,42,0.25)] transition-colors hover:bg-slate-700 active:bg-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-50 disabled:cursor-wait disabled:opacity-60"
                    >
                        {t('Email Password Reset Link')}
                    </button>

                    <p className="mt-4 text-center text-sm">
                        <Link href={route('login')} className="inline-flex min-h-10 items-center rounded-lg px-3 font-medium text-slate-500 transition-colors hover:text-slate-900 hover:underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                            {t('Back to Sign In')}
                        </Link>
                    </p>
                </form>
            </div>
        </GuestLayout>
    );
}
