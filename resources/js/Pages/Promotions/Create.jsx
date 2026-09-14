import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head } from '@inertiajs/react';
import PromotionForm from './PromotionForm';

export default function Create() {
    const t = useTranslation();

    return (
        <AuthenticatedLayout>
            <Head title={t('New Banner')} />
            <h1 className="mb-6 text-2xl font-bold text-gray-900">{t('New Banner')}</h1>
            <PromotionForm mode="create" promotion={null} />
        </AuthenticatedLayout>
    );
}
