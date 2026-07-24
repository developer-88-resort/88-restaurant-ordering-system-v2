import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import CategoryForm from './CategoryForm';

export default function Edit({ category, existingCategories }) {
    const t = useTranslation();
    const { data, setData, put, processing, errors } = useForm({
        name: category.name,
        sort_order: category.sort_order,
        is_active: category.is_active,
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('menu-categories.update', category.id));
    };

    return (
        <AuthenticatedLayout>
            <Head title={`${t('Edit Category')} — ${category.name}`} />
            <CategoryForm
                title={`${t('Edit Category')} — ${category.name}`}
                existingCategories={existingCategories}
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                onSubmit={submit}
                submitLabel={t('Save Changes')}
            />
        </AuthenticatedLayout>
    );
}
