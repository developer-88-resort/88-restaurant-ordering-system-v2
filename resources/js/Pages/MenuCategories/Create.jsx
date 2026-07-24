import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import CategoryForm from './CategoryForm';

export default function Create({ existingCategories, nextSortOrder }) {
    const t = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        sort_order: nextSortOrder,
        is_active: true,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('menu-categories.store'));
    };

    return (
        <AuthenticatedLayout>
            <Head title={t('New Menu Category')} />
            <CategoryForm
                title={t('New Menu Category')}
                existingCategories={existingCategories}
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                onSubmit={submit}
                submitLabel={t('Create Category')}
            />
        </AuthenticatedLayout>
    );
}
