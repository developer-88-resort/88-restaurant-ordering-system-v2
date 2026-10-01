import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, usePage } from '@inertiajs/react';
import AccountInfo from './Partials/AccountInfo';
import AvatarUploadForm from './Partials/AvatarUploadForm';
import ChangePinForm from './Partials/ChangePinForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
    const t = useTranslation();
    const user = usePage().props.auth.user;

    return (
        <AuthenticatedLayout>
            <Head title={t('Profile')} />

            <div className="mx-auto max-w-7xl">
            <h1 className="text-2xl font-bold tracking-tight text-slate-900 mb-7 sm:text-3xl">{t('Account Settings')}</h1>

            <div className="grid grid-cols-1 gap-6 items-start lg:grid-cols-[minmax(0,1fr)_320px] xl:grid-cols-[minmax(0,1fr)_360px]">
                <div className="min-w-0 space-y-6">
                    <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                    {user.uses_pin && <ChangePinForm />}
                    {/* A PIN-only account has no password to change. */}
                    {user.has_password && <UpdatePasswordForm />}
                </div>

                <div className="space-y-6">
                    <AvatarUploadForm />
                    <AccountInfo />
                </div>
            </div>
            </div>
        </AuthenticatedLayout>
    );
}
