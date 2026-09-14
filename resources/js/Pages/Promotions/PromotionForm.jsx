import SingleImageUploader from '@/Components/SingleImageUploader';
import { useTranslation } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

const inputClasses = 'block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm';
const checkboxClasses = 'rounded border-gray-300 text-[#8A3330] shadow-sm focus:ring-[#8A3330]';

export default function PromotionForm({ mode, promotion }) {
    const t = useTranslation();
    const isEdit = mode === 'edit';

    const { data, setData, errors, processing, transform, post } = useForm({
        starts_at: promotion?.starts_at ? promotion.starts_at.slice(0, 16) : '',
        ends_at: promotion?.ends_at ? promotion.ends_at.slice(0, 16) : '',
        is_published: promotion?.is_published ?? false,
        is_disabled: promotion?.is_disabled ?? false,
        banner_cta_url: promotion?.banner_cta_url ?? '',
    });

    const [newImage, setNewImage] = useState(null);
    const [removedImage, setRemovedImage] = useState(false);
    const [newMobileImage, setNewMobileImage] = useState(null);
    const [removedMobileImage, setRemovedMobileImage] = useState(false);

    const submit = (e) => {
        e.preventDefault();

        transform((formData) => ({
            ...formData,
            // Omit the key entirely when no new file was picked — sending an
            // explicit null gets stringified to the literal text "null" by
            // FormData, which then fails the backend's `image` MIME rule
            // even though nothing was actually meant to change.
            ...(newImage?.file ? { image: newImage.file } : {}),
            remove_image: removedImage,
            ...(newMobileImage?.file ? { mobile_image: newMobileImage.file } : {}),
            remove_mobile_image: removedMobileImage,
            ...(isEdit ? { _method: 'put' } : {}),
        }));

        const options = { forceFormData: true };

        if (isEdit) {
            post(route('superadmin.promotions.update', promotion.id), options);
        } else {
            post(route('superadmin.promotions.store'), options);
        }
    };

    return (
        <form onSubmit={submit}>
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <section className="rounded-xl border border-[#E5DDD0] bg-white p-6">
                        <h3 className="mb-4 text-base font-semibold text-gray-900">{t('Desktop Side Ad')}</h3>
                        <SingleImageUploader
                            existingImageUrl={promotion?.image_url}
                            newFile={newImage}
                            onFileChange={setNewImage}
                            removed={removedImage}
                            onRemovedChange={setRemovedImage}
                            error={errors.image}
                        />
                        <p className="mt-2 text-xs text-gray-400">{t('Design the whole banner into the image itself — this is exactly what guests will see.')}</p>
                    </section>

                    <section className="rounded-xl border border-[#E5DDD0] bg-white p-6">
                        <h3 className="mb-4 text-base font-semibold text-gray-900">{t('Mobile Banner')}</h3>
                        <SingleImageUploader
                            existingImageUrl={promotion?.mobile_image_url}
                            newFile={newMobileImage}
                            onFileChange={setNewMobileImage}
                            removed={removedMobileImage}
                            onRemovedChange={setRemovedMobileImage}
                            error={errors.mobile_image}
                            wide
                        />
                        <p className="mt-2 text-xs text-gray-400">
                            {t('Optional but recommended. Upload a horizontal 3:1 image (for example 1200 × 400 px) for a sharp, uncropped phone banner. The desktop image is used as fallback.')}
                        </p>
                    </section>

                    <section className="rounded-xl border border-[#E5DDD0] bg-white p-6">
                        <h3 className="mb-4 text-base font-semibold text-gray-900">{t('Link URL')}</h3>
                        <input
                            id="banner_cta_url"
                            type="url"
                            placeholder="https://..."
                            value={data.banner_cta_url}
                            onChange={(e) => setData('banner_cta_url', e.target.value)}
                            className={inputClasses}
                        />
                        <p className="mt-1 text-xs text-gray-400">{t('Optional — where guests go if they tap this banner.')}</p>
                        {errors.banner_cta_url && <p className="mt-2 text-sm text-red-600">{errors.banner_cta_url}</p>}
                    </section>

                    <section className="rounded-xl border border-[#E5DDD0] bg-white p-6">
                        <h3 className="mb-4 text-base font-semibold text-gray-900">{t('Schedule')}</h3>
                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div>
                                <label htmlFor="starts_at" className="block text-sm font-medium text-gray-700">{t('Start Date & Time')}</label>
                                <input id="starts_at" type="datetime-local" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} className={inputClasses} />
                                <p className="mt-1 text-xs text-gray-400">{t('Leave blank to start immediately.')}</p>
                                {errors.starts_at && <p className="mt-2 text-sm text-red-600">{errors.starts_at}</p>}
                            </div>
                            <div>
                                <label htmlFor="ends_at" className="block text-sm font-medium text-gray-700">{t('End Date & Time')}</label>
                                <input id="ends_at" type="datetime-local" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} className={inputClasses} />
                                <p className="mt-1 text-xs text-gray-400">{t('Leave blank for an open-ended banner.')}</p>
                                {errors.ends_at && <p className="mt-2 text-sm text-red-600">{errors.ends_at}</p>}
                            </div>
                        </div>
                    </section>
                </div>

                <div className="space-y-6">
                    <section className="rounded-xl border border-[#E5DDD0] bg-white p-6">
                        <h3 className="mb-4 text-base font-semibold text-gray-900">{t('Status')}</h3>
                        <label className="flex items-start gap-2.5 rounded-lg border border-[#EEE6DC] bg-[#FCFAF7] p-3.5">
                            <input type="checkbox" checked={data.is_published} onChange={(e) => setData('is_published', e.target.checked)} className={`${checkboxClasses} mt-0.5`} />
                            <span>
                                <span className="block text-sm font-semibold text-gray-900">{t('Published')}</span>
                                <span className="block text-xs text-gray-500">{t('Unpublished banners stay as Draft, hidden from customers regardless of schedule.')}</span>
                            </span>
                        </label>
                        <label className="mt-3 flex items-start gap-2.5 rounded-lg border border-[#EEE6DC] bg-[#FCFAF7] p-3.5">
                            <input type="checkbox" checked={data.is_disabled} onChange={(e) => setData('is_disabled', e.target.checked)} className={`${checkboxClasses} mt-0.5`} />
                            <span>
                                <span className="block text-sm font-semibold text-gray-900">{t('Temporarily Disable')}</span>
                                <span className="block text-xs text-gray-500">{t('Overrides everything else — a disabled banner never shows, even if published and within its schedule.')}</span>
                            </span>
                        </label>
                    </section>
                </div>
            </div>

            <div className="sticky bottom-0 z-10 -mx-4 mt-6 flex items-center justify-end space-x-3 border-t border-[#E5DDD0] bg-[#F7F0E3]/95 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6">
                <Link href={route('superadmin.promotions.index')} className="text-sm text-gray-600 hover:text-gray-900">
                    {t('Cancel')}
                </Link>
                <button
                    type="submit"
                    disabled={processing}
                    className="inline-flex items-center rounded-md border border-transparent bg-[#8A3330] px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#742927] disabled:opacity-70"
                >
                    {isEdit ? t('Save Changes') : t('Create Banner')}
                </button>
            </div>
        </form>
    );
}
