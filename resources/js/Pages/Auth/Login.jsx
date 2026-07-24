import GuestLayout from '@/Layouts/GuestLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Login({ canResetPassword }) {
    const t = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });
    const [showPassword, setShowPassword] = useState(false);

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Management Portal" />

            <div className="w-full max-w-sm">
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

                    {Object.keys(errors).length > 0 && (
                        <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                            <p className="text-sm text-red-700">{Object.values(errors)[0]}</p>
                        </div>
                    )}

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-xl bg-[#8A3330] hover:bg-[#742927] text-white font-semibold py-3 transition duration-200 disabled:opacity-70"
                    >
                        {t('Sign in')}
                    </button>

                    {canResetPassword && (
                        <p className="mt-4 text-center text-sm">
                            <a href={route('password.request')} className="text-[#8A3330] hover:underline font-medium">
                                {t('Forgot your password?')}
                            </a>
                        </p>
                    )}
                </form>

                <p className="mt-8 text-center text-sm text-[#8A7B6D]">
                    {t('Authorized personnel only. Contact management for access.')}
                </p>
            </div>
        </GuestLayout>
    );
}
