import { useTranslation } from '@/lib/i18n';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';

export default function UpdateProfileInformation({ mustVerifyEmail, status }) {
    const t = useTranslation();
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <section className="bg-white border border-[#E5DDD0] rounded-xl p-6">
            <h3 className="text-base font-semibold text-gray-900">{t('Profile Information')}</h3>
            <p className="text-sm text-gray-500 mt-1">{t("Update your account's profile information and email address.")}</p>

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
                        className="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm"
                    />
                    {errors.name && <p className="text-sm text-red-600 mt-2">{errors.name}</p>}
                </div>

                <div>
                    <label htmlFor="email" className="block text-sm font-medium text-gray-700">{t('Email')}</label>
                    <input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                        className="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm"
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
