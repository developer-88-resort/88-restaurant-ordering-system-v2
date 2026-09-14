import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head } from '@inertiajs/react';
import PromotionForm from './PromotionForm';

export default function Edit({ promotion }) {
    const t = useTranslation();

    return (
        <AuthenticatedLayout>
            <Head title={t('Edit Banner')} />
            <h1 className="mb-6 text-2xl font-bold text-gray-900">{t('Edit Banner')}</h1>
            <PromotionForm mode="edit" promotion={promotion} />
        </AuthenticatedLayout>
    );
}
