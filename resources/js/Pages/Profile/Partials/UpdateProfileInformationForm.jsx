import { useTranslation } from '@/lib/i18n';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';

export default function UpdateProfileInformation({ mustVerifyEmail, status }) {
    const t = useTranslation();
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 className="text-base font-semibold tracking-tight text-slate-900">{t('Profile Information')}</h3>
            <p className="text-sm leading-6 text-slate-500 mt-1">{t("Update your account's profile information and email address.")}</p>

            <form onSubmit={submit} className="mt-6 space-y-5">
                <div>
                    <label htmlFor="name" className="block text-sm font-medium text-gray-700">{t('Name')}</label>
                    <input
                        id="name"
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        autoFocus
                        autoComplete="name"
                        className="mt-2 block h-12 w-full rounded-xl border-slate-200 bg-slate-50/50 px-4 text-sm text-slate-900 shadow-none transition-colors focus:border-slate-400 focus:bg-white focus:ring-2 focus:ring-slate-200"
                    />
                    {errors.name && <p className="text-sm text-red-600 mt-2">{errors.name}</p>}
                </div>

                <div>
                    <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                        {t('Email')}
                        {user.uses_pin && <span className="ml-1 font-normal text-gray-400">({t('optional')})</span>}
                    </label>
                    <input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required={!user.uses_pin}
                        autoComplete="username"
                        className="mt-2 block h-12 w-full rounded-xl border-slate-200 bg-slate-50/50 px-4 text-sm text-slate-900 shadow-none transition-colors focus:border-slate-400 focus:bg-white focus:ring-2 focus:ring-slate-200"
                    />
                    {errors.email && <p className="text-sm text-red-600 mt-2">{errors.email}</p>}

                    {mustVerifyEmail && user.email_verified_at === null && (
                        <div className="mt-2">
                            <p className="text-sm text-gray-600">
                                {t('Your email address is unverified.')}{' '}
                                <Link
                                    href={route('verification.send')}
                                    method="post"
                                    as="button"
                                    className="underline hover:text-gray-900"
                                >
                                    {t('Click here to re-send the verification email.')}
                                </Link>
                            </p>
                            {status === 'verification-link-sent' && (
                                <p className="mt-2 text-sm font-medium text-green-600">
                                    {t('A new verification link has been sent to your email address.')}
                                </p>
                            )}
                        </div>
                    )}
                </div>

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
