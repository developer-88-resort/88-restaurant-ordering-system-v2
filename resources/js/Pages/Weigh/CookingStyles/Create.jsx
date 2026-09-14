import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import SetForm from './SetForm';

export default function Create({ availableStyles, nextSortOrder }) {
    const t = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        sort_order: nextSortOrder,
        is_active: true,
        cooking_style_ids: [],
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('weigh.cooking-styles.store'));
    };

    return (
        <AuthenticatedLayout>
            <Head title={t('New Cooking Style Set')} />
            <SetForm
                title={t('New Cooking Style Set')}
                availableStyles={availableStyles}
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                onSubmit={submit}
                submitLabel={t('Create Set')}
            />
        </AuthenticatedLayout>
    );
}
