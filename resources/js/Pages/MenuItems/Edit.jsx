import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head } from '@inertiajs/react';
import ItemForm from './ItemForm';

export default function Edit({ item, categories, availabilityOptions }) {
    const t = useTranslation();

    return (
        <AuthenticatedLayout>
            <Head title={`${t('Edit Item')} — ${item.name}`} />
            <h1 className="text-2xl font-bold text-gray-900 mb-6">{t('Edit Item')} — {item.name}</h1>
            <ItemForm mode="edit" item={item} categories={categories} availabilityOptions={availabilityOptions} nextSortOrders={null} />
        </AuthenticatedLayout>
    );
}
