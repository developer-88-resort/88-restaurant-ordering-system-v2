import Avatar from '@/Components/Avatar';
import { useTranslation } from '@/lib/i18n';
import { router, useForm, usePage } from '@inertiajs/react';

export default function AvatarUploadForm() {
    const t = useTranslation();
    const user = usePage().props.auth.user;
    const { setData, post, processing } = useForm({ avatar: null });

    const handleFile = (e) => {
        const file = e.target.files[0];
        if (!file) return;
        setData('avatar', file);
        post(route('profile.avatar.update'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setData('avatar', null),
        });
    };

    const removeAvatar = () => {
        if (confirm(t('Remove your profile picture?'))) {
            router.delete(route('profile.avatar.destroy'), { preserveScroll: true });
        }
    };

    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 className="text-base font-semibold tracking-tight text-slate-900">{t('Profile Picture')}</h3>
            <p className="text-sm leading-6 text-slate-500 mt-1 mb-5">{t('Shown in the sidebar and wherever your name appears.')}</p>

            <div className="flex flex-col items-center gap-5 rounded-xl border border-slate-100 bg-slate-50/60 p-5">
                <Avatar user={user} className="h-24 w-24 text-2xl ring-4 ring-white shadow-sm" />

                <div className="flex flex-col gap-2 w-full">
                    <label className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm cursor-pointer transition-colors hover:border-slate-300 hover:bg-slate-50 focus-within:ring-2 focus-within:ring-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="h-4 w-4">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 16.5V9.75m0 0l-3.75 3.75M12 9.75l3.75 3.75M3 17.25V18a2.25 2.25 0 002.25 2.25h13.5A2.25 2.25 0 0021 18v-.75" />
                        </svg>
                        {processing ? t('Uploading...') : t('Upload Photo')}
                        <input type="file" accept="image/*" className="sr-only" onChange={handleFile} disabled={processing} />
                    </label>

                    {user.avatar_url && (
                        <button
                            type="button"
                            onClick={removeAvatar}
                            className="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-md text-sm font-medium text-red-600 hover:bg-red-50 transition"
                        >
                            {t('Remove Photo')}
                        </button>
                    )}
                </div>
            </div>
        </section>
    );
}
