import PasswordRequirements from '@/Components/PasswordRequirements';
import { useTranslation } from '@/lib/i18n';
import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { useRef } from 'react';

export default function UpdatePasswordForm() {
    const t = useTranslation();
    const passwordInput = useRef();
    const currentPasswordInput = useRef();

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current.focus();
                }
                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current.focus();
                }
            },
        });
    };

    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 className="text-base font-semibold tracking-tight text-slate-900">{t('Update Password')}</h3>
            <p className="text-sm leading-6 text-slate-500 mt-1">{t('Ensure your account is using a long, random password to stay secure.')}</p>

            <form onSubmit={updatePassword} className="mt-6 space-y-5">
                <div>
                    <label htmlFor="current_password" className="block text-sm font-medium text-gray-700">{t('Current Password')}</label>
                    <input
                        id="current_password"
                        ref={currentPasswordInput}
                        type="password"
                        value={data.current_password}
                        onChange={(e) => setData('current_password', e.target.value)}
                        autoComplete="current-password"
                        className="mt-2 block h-12 w-full rounded-xl border-slate-200 bg-slate-50/50 px-4 text-sm text-slate-900 shadow-none transition-colors focus:border-slate-400 focus:bg-white focus:ring-2 focus:ring-slate-200"
                    />
                    {errors.current_password && <p className="text-sm text-red-600 mt-2">{errors.current_password}</p>}
                </div>

                <div>
                    <label htmlFor="password" className="block text-sm font-medium text-gray-700">{t('New Password')}</label>
                    <input
                        id="password"
                        ref={passwordInput}
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="new-password"
                        className="mt-2 block h-12 w-full rounded-xl border-slate-200 bg-slate-50/50 px-4 text-sm text-slate-900 shadow-none transition-colors focus:border-slate-400 focus:bg-white focus:ring-2 focus:ring-slate-200"
                    />
                    {errors.password && <p className="text-sm text-red-600 mt-2">{errors.password}</p>}
                </div>

                <div className="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                    <PasswordRequirements password={data.password} />
                </div>

                <div>
                    <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-700">{t('Confirm Password')}</label>
                    <input
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        autoComplete="new-password"
                        className="mt-2 block h-12 w-full rounded-xl border-slate-200 bg-slate-50/50 px-4 text-sm text-slate-900 shadow-none transition-colors focus:border-slate-400 focus:bg-white focus:ring-2 focus:ring-slate-200"
                    />
                    {errors.password_confirmation && <p className="text-sm text-red-600 mt-2">{errors.password_confirmation}</p>}
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
