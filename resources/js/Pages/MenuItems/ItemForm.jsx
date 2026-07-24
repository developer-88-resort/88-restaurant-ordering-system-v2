import ImageUploader from '@/Components/ImageUploader';
import VariantRow from '@/Components/VariantRow';
import { useTranslation } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function ItemForm({ mode, item, categories, availabilityOptions, nextSortOrders }) {
    const t = useTranslation();
    const isEdit = mode === 'edit';

    const [variants, setVariants] = useState(
        () =>
            item?.variants.map((v) => ({
                id: v.id,
                name: v.name,
                sku: v.sku ?? '',
                price: v.price,
                existingImageUrl: v.image_url,
                newImageFile: null,
                newImagePreview: null,
                removeImage: false,
            })) ?? [],
    );
    const [defaultIndex, setDefaultIndex] = useState(() => {
        const idx = item?.variants.findIndex((v) => v.is_default) ?? -1;
        return idx >= 0 ? idx : null;
    });

    const [removedImageIds, setRemovedImageIds] = useState([]);
    const [primaryImageId, setPrimaryImageId] = useState(() => {
        const primary = item?.images.find((i) => i.is_primary);
        return primary?.id ?? item?.images[0]?.id ?? null;
    });
    const [newImages, setNewImages] = useState([]);

    const firstCategoryId = categories[0]?.id ?? '';
    const { data, setData, errors, processing, transform, post } = useForm({
        name: item?.name ?? '',
        menu_category_id: item?.menu_category_id ?? firstCategoryId,
        description: item?.description ?? '',
        price: item?.price ?? '',
        sku: item?.sku ?? '',
        prep_time_minutes: item?.prep_time_minutes ?? '',
        availability_status: item?.availability_status ?? 'available',
        sort_order: item?.sort_order ?? (nextSortOrders?.[firstCategoryId] ?? 0),
        is_featured: item?.is_featured ?? false,
        is_best_seller: item?.is_best_seller ?? false,
    });

    const [sortOrderTouched, setSortOrderTouched] = useState(isEdit);

    const onCategoryChange = (categoryId) => {
        setData((current) => ({
            ...current,
            menu_category_id: categoryId,
            sort_order: !isEdit && !sortOrderTouched ? nextSortOrders?.[categoryId] ?? 0 : current.sort_order,
        }));
    };

    const addVariant = () => {
        setVariants((current) => [...current, { id: null, name: '', sku: '', price: '', existingImageUrl: null, newImageFile: null, newImagePreview: null, removeImage: false }]);
        setDefaultIndex((current) => (current === null ? variants.length : current));
    };

    const removeVariant = (index) => {
        setVariants((current) => current.filter((_, i) => i !== index));
        setDefaultIndex((current) => {
            if (current === index) return variants.length > 1 ? 0 : null;
            if (current !== null && current > index) return current - 1;
            return current;
        });
    };

    const updateVariant = (index, changes) => {
        setVariants((current) => current.map((v, i) => (i === index ? { ...v, ...changes } : v)));
    };

    const submit = (e) => {
        e.preventDefault();

        transform((formData) => ({
            ...formData,
            variants: variants.map((v) => ({
                id: v.id,
                name: v.name,
                sku: v.sku,
                price: v.price,
                image: v.newImageFile,
                remove_image: v.removeImage,
            })),
            default_variant_index: defaultIndex,
            images: newImages.map((f) => f.file),
            remove_images: removedImageIds,
            primary_image_id: primaryImageId,
            // PHP never populates $_FILES/parses a multipart body for a
            // literal PUT request (only POST) — Inertia doesn't spoof this
            // automatically, so an edit with files must go out as a POST
            // carrying _method=put, exactly like Blade's @method('PUT').
            ...(isEdit ? { _method: 'put' } : {}),
        }));

        if (isEdit) {
            post(route('menu-items.update', item.id), { forceFormData: true });
        } else {
            post(route('menu-items.store'), { forceFormData: true });
        }
    };

    return (
        <form onSubmit={submit}>
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                <div className="lg:col-span-2 space-y-6">
                    <section className="bg-white border border-[#E5DDD0] rounded-xl p-6">
                        <h3 className="text-base font-semibold text-gray-900 mb-4">{t('Basic Information')}</h3>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label htmlFor="name" className="block text-sm font-medium text-gray-700">{t('Name')}</label>
                                <input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                    autoFocus
                                    className="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm"
                                />
                                {errors.name && <p className="text-sm text-red-600 mt-2">{errors.name}</p>}
                            </div>
                            <div>
                                <label htmlFor="menu_category_id" className="block text-sm font-medium text-gray-700">{t('Category')}</label>
                                <select
                                    id="menu_category_id"
                                    value={data.menu_category_id}
                                    onChange={(e) => onCategoryChange(e.target.value)}
                                    required
                                    className="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm"
                                >
                                    {categories.map((category) => (
                                        <option key={category.id} value={category.id}>
                                            {category.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.menu_category_id && <p className="text-sm text-red-600 mt-2">{errors.menu_category_id}</p>}
                            </div>
                        </div>

                        <div className="mt-5">
                            <label htmlFor="description" className="block text-sm font-medium text-gray-700">{t('Description')}</label>
                            <textarea
                                id="description"
                                rows={2}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                className="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm"
                            />
                            {errors.description && <p className="text-sm text-red-600 mt-2">{errors.description}</p>}
                        </div>
                    </section>

                    <section className="bg-white border border-[#E5DDD0] rounded-xl p-6">
                        <h3 className="text-base font-semibold text-gray-900 mb-4">{t('Pricing & Prep')}</h3>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
                            <div>
                                <label htmlFor="price" className="block text-sm font-medium text-gray-700">{t('Price')}</label>
                                <input
                                    id="price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.price}
                                    onChange={(e) => setData('price', e.target.value)}
                                    className="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm"
                                />
                                <p className="mt-1 text-xs text-gray-400">{t('Required unless you add variants below.')}</p>
                                {errors.price && <p className="text-sm text-red-600 mt-2">{errors.price}</p>}
                            </div>
                            <div>
                                <label htmlFor="sku" className="block text-sm font-medium text-gray-700">{t('SKU (optional)')}</label>
                                <input
                                    id="sku"
                                    type="text"
                                    value={data.sku}
                                    onChange={(e) => setData('sku', e.target.value)}
                                    className="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm"
                                />
                                {errors.sku && <p className="text-sm text-red-600 mt-2">{errors.sku}</p>}
                            </div>
                            <div>
                                <label htmlFor="prep_time_minutes" className="block text-sm font-medium text-gray-700">{t('Prep Time (min)')}</label>
                                <input
                                    id="prep_time_minutes"
                                    type="number"
                                    min="0"
                                    value={data.prep_time_minutes}
                                    onChange={(e) => setData('prep_time_minutes', e.target.value)}
                                    className="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm"
                                />
                                {errors.prep_time_minutes && <p className="text-sm text-red-600 mt-2">{errors.prep_time_minutes}</p>}
                            </div>
                        </div>
                    </section>

                    <section className="bg-white border border-[#E5DDD0] rounded-xl p-6">
                        <h3 className="text-base font-semibold text-gray-900">{t('Variants')}</h3>
                        <p className="text-sm text-gray-500 mt-1 mb-4">
                            {t('Optional — use instead of creating duplicate items for things like Solo/Medium/Large, Spicy/Oriental, or Half/Whole. Leave empty if this item is just sold at the Price above.')}
                        </p>

                        {variants.map((variant, index) => (
                            <VariantRow
                                key={index}
                                variant={variant}
                                index={index}
                                isDefault={defaultIndex === index}
                                onChange={updateVariant}
                                onRemove={removeVariant}
                                onSetDefault={setDefaultIndex}
                            />
                        ))}

                        {variants.length > 0 && (
                            <p className="text-xs text-gray-400 mb-2">
                                {t('"Default" is pre-selected when this item is ordered. Only add a photo if this variant looks different from the item photo above (e.g. a different sauce/color) — otherwise leave it blank.')}
                            </p>
                        )}

                        <button type="button" onClick={addVariant} className="text-sm font-medium text-[#8A3330] hover:underline">
                            + {t('Add Variant')}
                        </button>
                        {errors.variants && <p className="text-sm text-red-600 mt-2">{errors.variants}</p>}
                    </section>
                </div>

                <div className="space-y-6">
                    <section className="bg-white border border-[#E5DDD0] rounded-xl p-6">
                        <h3 className="text-base font-semibold text-gray-900">{t('Images')}</h3>
                        <p className="text-sm text-gray-500 mt-1 mb-4">{t('First image (or whichever is Primary) shows on the grid, receipts, and the customer menu.')}</p>
                        <ImageUploader
                            existingImages={item?.images ?? []}
                            primaryId={primaryImageId}
                            onPrimaryChange={setPrimaryImageId}
                            removedIds={removedImageIds}
                            onRemovedChange={setRemovedImageIds}
                            newFiles={newImages}
                            onNewFilesChange={setNewImages}
                            error={errors.images}
                        />
                    </section>

                    <section className="bg-white border border-[#E5DDD0] rounded-xl p-6">
                        <h3 className="text-base font-semibold text-gray-900 mb-4">{t('Availability & Flags')}</h3>
                        <div>
                            <label htmlFor="availability_status" className="block text-sm font-medium text-gray-700">{t('Availability')}</label>
                            <select
                                id="availability_status"
                                value={data.availability_status}
                                onChange={(e) => setData('availability_status', e.target.value)}
                                className="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm"
                            >
                                {availabilityOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            {errors.availability_status && <p className="text-sm text-red-600 mt-2">{errors.availability_status}</p>}
                        </div>

                        <div className="mt-5">
                            <label htmlFor="sort_order" className="block text-sm font-medium text-gray-700">{t('Sort Order')}</label>
                            <input
                                id="sort_order"
                                type="number"
                                min="0"
                                value={data.sort_order}
                                onChange={(e) => {
                                    setSortOrderTouched(true);
                                    setData('sort_order', e.target.value);
                                }}
                                className="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm"
                            />
                            <p className="mt-1 text-xs text-gray-400">{t('Auto-filled with the next number in this category.')}</p>
                            {errors.sort_order && <p className="text-sm text-red-600 mt-2">{errors.sort_order}</p>}
                        </div>

                        <div className="mt-5 flex flex-col gap-3">
                            <label className="inline-flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={data.is_featured}
                                    onChange={(e) => setData('is_featured', e.target.checked)}
                                    className="rounded border-gray-300 text-[#8A3330] shadow-sm focus:ring-[#8A3330]"
                                />
                                <span className="text-sm text-gray-600">{t('Featured')}</span>
                            </label>
                            <label className="inline-flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={data.is_best_seller}
                                    onChange={(e) => setData('is_best_seller', e.target.checked)}
                                    className="rounded border-gray-300 text-[#8A3330] shadow-sm focus:ring-[#8A3330]"
                                />
                                <span className="text-sm text-gray-600">{t('Best Seller')}</span>
                            </label>
                        </div>
                    </section>
                </div>
            </div>

            <div className="sticky bottom-0 z-10 -mx-4 sm:-mx-6 mt-6 border-t border-[#E5DDD0] bg-[#F7F0E3]/95 backdrop-blur px-4 sm:px-6 py-4 flex items-center justify-end space-x-3">
                <Link href={route('menu-items.index')} className="text-sm text-gray-600 hover:text-gray-900">
                    {t('Cancel')}
                </Link>
                <button
                    type="submit"
                    disabled={processing}
                    className="inline-flex items-center px-4 py-2 bg-[#8A3330] border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-[#742927] transition ease-in-out duration-150 disabled:opacity-70"
                >
                    {isEdit ? t('Save Changes') : t('Create Item')}
                </button>
            </div>
        </form>
    );
}
