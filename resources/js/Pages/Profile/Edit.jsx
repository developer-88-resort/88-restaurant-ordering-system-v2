import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head } from '@inertiajs/react';
import AccountInfo from './Partials/AccountInfo';
import AvatarUploadForm from './Partials/AvatarUploadForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
    const t = useTranslation();

    return (
        <AuthenticatedLayout>
            <Head title={t('Profile')} />

            <h1 className="text-2xl font-bold text-gray-900 mb-6">{t('Account Settings')}</h1>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                <div className="lg:col-span-2 space-y-6">
                    <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                    <UpdatePasswordForm />
                </div>

                <div className="space-y-6">
                    <AvatarUploadForm />
                    <AccountInfo />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
