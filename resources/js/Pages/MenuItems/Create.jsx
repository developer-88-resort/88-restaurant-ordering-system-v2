import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head } from '@inertiajs/react';
import ItemForm from './ItemForm';

export default function Create({ categories, availabilityOptions, nextSortOrders, cookingStyles, weighed }) {
    const t = useTranslation();

    return (
        <AuthenticatedLayout>
            <Head title={t('New Menu Item')} />
            <h1 className="text-2xl font-bold text-gray-900 mb-6">{t('New Menu Item')}</h1>
            <ItemForm mode="create" item={null} categories={categories} availabilityOptions={availabilityOptions} nextSortOrders={nextSortOrders} cookingStyles={cookingStyles} weighed={weighed} />
        </AuthenticatedLayout>
    );
}
