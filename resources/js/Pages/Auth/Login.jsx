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
        <GuestLayout>
            <Head title="Management Portal" />

            <div className={`w-full ${mode === 'pin' ? 'py-6' : 'py-10'} ${mode === 'names' ? 'max-w-3xl' : 'max-w-sm'}`}>
                {/* The PIN pad drops the brand block: the person's own avatar
                    heads it instead, and the keypad has to fit a landscape tablet. */}
                {mode !== 'pin' && (
                    <div className="flex flex-col items-center mb-8">
                        <img src="/images/logo.png" alt="88 Hot Spring Resort" className="h-28 w-auto object-contain mb-4" />

                        <h1
                            className="text-[29px] font-medium text-center text-black leading-none"
                            style={{ fontFamily: "'Playfair Display', serif" }}
                        >
                            88 Hot Spring Resort
                        </h1>

                        <p className="text-[#6F6258] text-lg mt-1 font-normal">{t('Management Portal')}</p>
                    </div>
                )}

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
                    <p className="mt-8 text-center text-sm text-[#8A7B6D]">
                        {t('Authorized personnel only. Contact management for access.')}
                    </p>
                )}
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
            <h2 className="text-center text-base font-semibold text-[#3B2E29]">{t('Tap your name to sign in')}</h2>

            {users.length > 12 && (
                <input
                    type="search"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder={t('Find your name')}
                    className="mx-auto mt-4 block w-full max-w-sm rounded-xl border border-[#D9CCBA] bg-white px-4 py-3 text-sm outline-none focus:border-[#8A3330] focus:ring-2 focus:ring-[#8A3330]"
                />
            )}

            {/* Flex-wrap, not a grid: a short list stays centred instead of
                leaving empty columns on the right. */}
            <div className="mt-5 flex flex-wrap justify-center gap-3">
                {shown.map((user) => (
                    <button
                        key={user.id}
                        type="button"
                        onClick={() => onChoose(user)}
                        className="flex w-[calc(50%-0.375rem)] sm:w-44 flex-col items-center gap-2 rounded-2xl border border-[#E5DDD0] bg-white px-3 py-5 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)] transition hover:-translate-y-0.5 hover:border-[#CDAEA4] active:scale-[.98]"
                    >
                        <Avatar user={user} className="h-16 w-16 text-lg" />
                        <span className="w-full truncate text-center text-sm font-bold text-[#251C19]">{user.name}</span>
                        <span className="rounded-full bg-[#F8EAE6] px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.08em] text-[#8A3330]">
                            {user.role_label}
                        </span>
                    </button>
                ))}
            </div>

            {shown.length === 0 && <p className="mt-6 text-center text-sm text-[#8A7B6D]">{t('No names match your search.')}</p>}

            <p className="mt-8 text-center text-sm">
                <button type="button" onClick={onUseEmail} className="font-medium text-[#8A3330] hover:underline">
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
        <div className="rounded-3xl border border-[#E5DDD0] bg-white/70 p-5 shadow-[0_22px_60px_-48px_rgba(55,35,30,0.7)]">
            <div className="flex items-center gap-3">
                <button
                    type="button"
                    onClick={onBack}
                    className="rounded-lg px-2 py-1.5 text-sm font-semibold text-[#8A3330] hover:bg-[#F8EAE6]"
                >
                    ← {t('Not you?')}
                </button>
            </div>

            <div className="flex flex-col items-center text-center">
                <Avatar user={user} className="h-16 w-16 text-lg" />
                <p className="mt-3 text-lg font-bold text-[#251C19]">{user.name}</p>
                <p className="text-sm text-[#8A7B6D]">{t('Enter your PIN')}</p>
            </div>

            <div className="mt-5">
                <PinPad
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

            <div className="relative mb-4">
                <input
                    id="password"
                    type={showPassword ? 'text' : 'password'}
                    name="password"
                    placeholder={t('Password')}
                    autoComplete="current-password"
                    value={data.password}
                    required
                    onChange={(e) => setData('password', e.target.value)}
                    className="w-full rounded-xl border border-[#D9CCBA] bg-white px-4 py-3 pr-11 text-sm text-[#333] placeholder:text-gray-400 outline-none focus:border-[#8A3330] focus:ring-2 focus:ring-[#8A3330]"
                />
                <button
                    type="button"
                    onClick={() => setShowPassword((v) => !v)}
                    className="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-[#8A3330] transition-colors"
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
                className="w-full rounded-xl bg-[#8A3330] hover:bg-[#742927] text-white font-semibold py-3 transition duration-200 disabled:opacity-70"
            >
                {t('Sign in')}
            </button>

            {canResetPassword && (
                <p className="mt-4 text-center text-sm">
                    <Link href={route('password.request')} className="text-[#8A3330] hover:underline font-medium">
                        {t('Forgot your password?')}
                    </Link>
                </p>
            )}

            {onUsePin && (
                <p className="mt-3 text-center text-sm">
                    <button type="button" onClick={onUsePin} className="text-[#8A3330] hover:underline font-medium">
                        ← {t('Sign in with your name and PIN')}
                    </button>
                </p>
            )}
        </form>
    );
}
