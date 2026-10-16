import Avatar from '@/Components/Avatar';
import PinPad from '@/Components/PinPad';
import { ToastList } from '@/Components/Toast';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

/**
 * Staff and Admin tap their name, then enter their PIN. Superadmin (and
 * anyone without a PIN yet) uses "Sign in with email".
 */
export default function Login({ canResetPassword, pinUsers = [], pinLength }) {
    const t = useTranslation();
    const [mode, setMode] = useState(pinUsers.length ? 'names' : 'email');
    const [chosen, setChosen] = useState(null);

    const choose = (user) => {
        setChosen(user);
        setMode('pin');
    };

    return (
        <GuestLayout modern>
            <Head title="Management Portal" />

            <div className={mode === 'pin' ? 'w-full max-w-sm' : 'grid w-full max-w-6xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm lg:grid-cols-[320px_minmax(0,1fr)]'}>
                {mode !== 'pin' && (
                    <aside className="flex flex-col justify-between gap-8 border-b border-slate-200 bg-slate-50/70 p-6 sm:p-8 lg:border-b-0 lg:border-r lg:p-10">
                        <div className="flex items-center gap-4 lg:block">
                            <img src="/images/logo.png" alt="88 Hot Spring Resort" className="h-16 w-16 object-contain lg:mb-8 lg:h-20 lg:w-20" />
                            <div>
                                <h1 className="text-xl font-semibold leading-tight tracking-tight text-slate-900 lg:text-3xl">88 Hot Spring Resort</h1>
                                <p className="mt-2 text-sm text-slate-500">{t('Management Portal')}</p>
                            </div>
                        </div>
                        <div className="hidden lg:block">
                            <p className="text-xs leading-6 text-slate-500">{t('Authorized personnel only. Contact management for access.')}</p>
                            {/* A full page load (data-turbo="false"): /status is a plain Blade
                                page, and Turbo swapping it in and back again leaves this React
                                page blank. */}
                            <a href={route('status')} data-turbo="false" className="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 underline-offset-4 hover:underline">
                                <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                {t('System status')}
                            </a>
                        </div>
                    </aside>
                )}
                <div className={mode === 'pin' ? '' : 'min-w-0 p-6 sm:p-8 lg:p-10'}>
                {mode === 'names' && (
                    <NamePicker users={pinUsers} onChoose={choose} onUseEmail={() => setMode('email')} />
                )}

                {mode === 'pin' && chosen && (
                    <PinEntry user={chosen} pinLength={pinLength} onBack={() => setMode('names')} />
                )}

                {mode === 'email' && (
                    <EmailForm
                        canResetPassword={canResetPassword}
                        onUsePin={pinUsers.length ? () => setMode('names') : null}
                    />
                )}

                {mode !== 'pin' && (
                    <p className="mt-6 text-center text-xs leading-5 text-slate-500 lg:hidden">
                        {t('Authorized personnel only. Contact management for access.')}
                        <a href={route('status')} data-turbo="false" className="mt-1 block font-semibold text-slate-600 underline-offset-4 hover:underline">{t('System status')}</a>
                    </p>
                )}
                </div>
            </div>
        </GuestLayout>
    );
}

function NamePicker({ users, onChoose, onUseEmail }) {
    const t = useTranslation();
    const [search, setSearch] = useState('');

    const shown = useMemo(() => {
        const needle = search.trim().toLowerCase();
        return needle ? users.filter((user) => user.name.toLowerCase().includes(needle)) : users;
    }, [users, search]);

    return (
        <div>
            <h2 className="text-xl font-semibold tracking-tight text-slate-900">{t('Tap your name to sign in')}</h2>

            {users.length > 12 && (
                <input
                    type="search"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder={t('Find your name')}
                    aria-label={t('Find your name')}
                    className="mt-5 block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-300"
                />
            )}

            <div className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                {shown.map((user) => (
                    <button
                        key={user.id}
                        type="button"
                        onClick={() => onChoose(user)}
                        className="group flex min-h-20 min-w-0 items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 text-left transition-colors hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2"
                    >
                        <Avatar user={user} className="h-11 w-11 text-sm !border-slate-200 !bg-slate-100 !text-slate-600" />
                        <span className="min-w-0 flex-1">
                        <span className="block truncate text-sm font-semibold text-slate-900">{user.name}</span>
                        <span className="mt-1 inline-flex text-[10px] font-medium uppercase tracking-wider text-slate-500">
                            {user.role_label}
                        </span>
                        </span>
                    </button>
                ))}
            </div>

            {shown.length === 0 && <p className="mt-6 text-center text-sm text-slate-500">{t('No names match your search.')}</p>}

            <p className="mt-6 border-t border-slate-100 pt-5 text-center text-sm">
                <button type="button" onClick={onUseEmail} className="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    {t('Sign in with email instead')}
                </button>
            </p>
        </div>
    );
}

function PinEntry({ user, pinLength, onBack }) {
    const t = useTranslation();
    const { data, setData, post, processing, errors, clearErrors } = useForm({
        user_id: user.id,
        pin: '',
    });

    const change = (pin) => {
        if (errors.pin) clearErrors('pin');
        setData('pin', pin);
    };

    const submit = () =>
        post(route('login.pin'), {
            onError: () => setData('pin', ''),
        });

    return (
        <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_12px_40px_-24px_rgba(15,23,42,0.25)] sm:p-7">
            <div className="flex items-center gap-3">
                <button
                    type="button"
                    onClick={onBack}
                    className="rounded-lg px-2 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                >
                    {t('Not you?')}
                </button>
            </div>

            <div className="mt-4 flex items-center gap-4 border-b border-slate-100 pb-5">
                <Avatar user={user} className="h-14 w-14 text-lg !bg-slate-100 !text-slate-700 !border-slate-200" />
                <div>
                <p className="text-lg font-semibold text-slate-900">{user.name}</p>
                <p className="text-sm text-slate-500">{t('Enter your PIN')}</p>
                </div>
            </div>

            <div className="mt-5">
                <PinPad modern
                    value={data.pin}
                    onChange={change}
                    onSubmit={submit}
                    length={pinLength}
                    disabled={processing}
                    error={errors.pin}
                    submitLabel={t('Sign in')}
                />
            </div>
        </div>
    );
}

function EmailForm({ canResetPassword, onUsePin }) {
    const t = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });
    const [showPassword, setShowPassword] = useState(false);
    const [toasts, setToasts] = useState([]);

    useEffect(() => {
        const message = Object.values(errors)[0];
        if (message) setToasts([{ id: Date.now(), type: 'error', message }]);
    }, [errors]);

    const dismissToast = (id) => setToasts((current) => current.filter((toast) => toast.id !== id));

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <form onSubmit={submit} className="mx-auto w-full max-w-md lg:py-8">
            <input
                id="email"
                type="email"
                name="email"
                placeholder={t('Email')}
                aria-label={t('Email')}
                autoComplete="username"
                value={data.email}
                required
                autoFocus
                onChange={(e) => setData('email', e.target.value)}
                className="w-full mb-4 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-[#333] placeholder:text-gray-400 outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-300"
            />

            <div className="relative mb-4">
                <input
                    id="password"
                    type={showPassword ? 'text' : 'password'}
                    name="password"
                    placeholder={t('Password')}
                    aria-label={t('Password')}
                    autoComplete="current-password"
                    value={data.password}
                    required
                    onChange={(e) => setData('password', e.target.value)}
                    className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 pr-11 text-sm text-[#333] placeholder:text-gray-400 outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-300"
                />
                <button
                    type="button"
                    onClick={() => setShowPassword((v) => !v)}
                    className="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-slate-700 transition-colors"
                    aria-label={showPassword ? t('Hide password') : t('Show password')}
                >
                    {showPassword ? (
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor" className="h-5 w-5">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    ) : (
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor" className="h-5 w-5">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    )}
                </button>
            </div>

            <ToastList toasts={toasts} onDismiss={dismissToast} />

            <button
                type="submit"
                disabled={processing}
                className="w-full rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-semibold py-3 transition duration-200 disabled:opacity-70"
            >
                {t('Sign in')}
            </button>

            {/* Going back to the name list comes first: most people here
                opened this form by mistake, while a forgotten password is
                for the few who sign in with one. */}
            {onUsePin && (
                <p className="mt-4 text-center text-sm">
                    <button type="button" onClick={onUsePin} className="text-slate-700 hover:underline font-medium">
                        {t('Sign in with your name and PIN')}
                    </button>
                </p>
            )}

            {canResetPassword && (
                <p className={`${onUsePin ? 'mt-3' : 'mt-4'} text-center text-sm`}>
                    <Link href={route('password.request')} className="text-slate-700 hover:underline font-medium">
                        {t('Forgot your password?')}
                    </Link>
                </p>
            )}
        </form>
    );
}
