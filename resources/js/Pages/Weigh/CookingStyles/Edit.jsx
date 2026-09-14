import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import SetForm from './SetForm';

export default function Edit({ set, availableStyles }) {
    const t = useTranslation();
    const { data, setData, put, processing, errors } = useForm({
        name: set.name,
        description: set.description ?? '',
        sort_order: set.sort_order,
        is_active: set.is_active,
        cooking_style_ids: set.cooking_style_ids ?? [],
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('weigh.cooking-styles.update', set.id));
    };

    return (
        <AuthenticatedLayout>
            <Head title={`${t('Edit Set')} — ${set.name}`} />
            <SetForm
                title={`${t('Edit Set')} — ${set.name}`}
                availableStyles={availableStyles}
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
