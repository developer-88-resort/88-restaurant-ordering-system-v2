import ImageUploader from '@/Components/ImageUploader';
import VariantRow from '@/Components/VariantRow';
import { clearDraft, readDraft, writeDraft } from '@/draft-persistence';
import { useTranslation } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function ItemForm({ mode, item, categories, availabilityOptions, nextSortOrders, cookingStyles = [] }) {
    const t = useTranslation();
    const isEdit = mode === 'edit';
    const draftKey = `menu-item-form:${isEdit ? `edit-${item.id}` : 'new'}`;
    // Images/files never survive a refresh (blob: URLs die, Files can't be
    // JSON-serialized), so only the text/number fields below are persisted —
    // see draft-persistence.js's own sanitize() for the same rule elsewhere.
    const draft = readDraft(draftKey);

    const [variants, setVariants] = useState(() => {
        if (draft?.variants) {
            return draft.variants.map((v) => ({
                id: v.id ?? null,
                name: v.name ?? '',
                description: v.description ?? '',
                sku: v.sku ?? '',
                price: v.price ?? '',
                existingImageUrl: item?.variants.find((iv) => iv.id === v.id)?.image_url ?? null,
                newImageFile: null,
                newImagePreview: null,
                removeImage: false,
            }));
        }

        return (
            item?.variants.map((v) => ({
                id: v.id,
                name: v.name,
                description: v.description ?? '',
                sku: v.sku ?? '',
                price: v.price,
                existingImageUrl: v.image_url,
                newImageFile: null,
                newImagePreview: null,
                removeImage: false,
            })) ?? []
        );
    });
    const [defaultIndex, setDefaultIndex] = useState(() => {
        if (draft && typeof draft.default_variant_index !== 'undefined') return draft.default_variant_index;
        const idx = item?.variants.findIndex((v) => v.is_default) ?? -1;
        return idx >= 0 ? idx : null;
    });

    const [removedImageIds, setRemovedImageIds] = useState([]);
    const [primaryImageId, setPrimaryImageId] = useState(() => {
        const primary = item?.images.find((i) => i.is_primary);
        return primary?.id ?? item?.images[0]?.id ?? null;
    });
    const [newImages, setNewImages] = useState([]);

    const [cookingStyleIds, setCookingStyleIds] = useState(() => {
        if (draft?.cooking_style_ids) return draft.cooking_style_ids;
        return item?.cooking_style_ids ?? [];
    });

    const firstCategoryId = categories[0]?.id ?? '';
    const { data, setData, errors, processing, transform, post } = useForm({
        name: draft?.name ?? item?.name ?? '',
        menu_category_id: draft?.menu_category_id ?? item?.menu_category_id ?? firstCategoryId,
        description: draft?.description ?? item?.description ?? '',
        price: draft?.price ?? item?.price ?? '',
        pricing_type: draft?.pricing_type ?? item?.pricing_type ?? 'fixed',
        price_per_kilo: draft?.price_per_kilo ?? item?.price_per_kilo ?? '',
        min_weight_grams: draft?.min_weight_grams ?? item?.min_weight_grams ?? 250,
        weight_step_grams: draft?.weight_step_grams ?? item?.weight_step_grams ?? 10,
        allow_tare: draft?.allow_tare ?? item?.allow_tare ?? false,
        // Weighed items are handed over at the counter, so they are hidden
        // from the customer QR menu unless someone deliberately opts in.
        counter_only: draft?.counter_only ?? item?.counter_only ?? true,
        sku: draft?.sku ?? item?.sku ?? '',
        prep_time_minutes: draft?.prep_time_minutes ?? item?.prep_time_minutes ?? '',
        availability_status: draft?.availability_status ?? item?.availability_status ?? 'available',
        sort_order: draft?.sort_order ?? item?.sort_order ?? (nextSortOrders?.[firstCategoryId] ?? 0),
        is_featured: draft?.is_featured ?? item?.is_featured ?? false,
        is_best_seller: draft?.is_best_seller ?? item?.is_best_seller ?? false,
    });

    useEffect(() => {
        const timer = setTimeout(() => {
            writeDraft(draftKey, {
                name: data.name,
                menu_category_id: data.menu_category_id,
                description: data.description,
                price: data.price,
                pricing_type: data.pricing_type,
                price_per_kilo: data.price_per_kilo,
                min_weight_grams: data.min_weight_grams,
                weight_step_grams: data.weight_step_grams,
                allow_tare: data.allow_tare,
                counter_only: data.counter_only,
                cooking_style_ids: cookingStyleIds,
                sku: data.sku,
                prep_time_minutes: data.prep_time_minutes,
                availability_status: data.availability_status,
                sort_order: data.sort_order,
                is_featured: data.is_featured,
                is_best_seller: data.is_best_seller,
                variants: variants.map((v) => ({ id: v.id, name: v.name, description: v.description, sku: v.sku, price: v.price })),
                default_variant_index: defaultIndex,
            });
        }, 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data, variants, defaultIndex, cookingStyleIds]);

    const isPerKilo = data.pricing_type === 'per_kilo';

    const toggleCookingStyle = (styleId) => {
        setCookingStyleIds((current) =>
            current.includes(styleId) ? current.filter((id) => id !== styleId) : [...current, styleId],
        );
    };

    const [sortOrderTouched, setSortOrderTouched] = useState(isEdit);

    const onCategoryChange = (categoryId) => {
        setData((current) => ({
            ...current,
            menu_category_id: categoryId,
            sort_order: !isEdit && !sortOrderTouched ? nextSortOrders?.[categoryId] ?? 0 : current.sort_order,
        }));
    };

    const addVariant = () => {
        setVariants((current) => [...current, { id: null, name: '', description: '', sku: '', price: '', existingImageUrl: null, newImageFile: null, newImagePreview: null, removeImage: false }]);
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
            // Variants and cooking styles are mutually exclusive: a per-kilo
            // item is priced by the scale, so it sends styles and no
            // variants; a fixed item sends variants and no styles.
            variants: isPerKilo
                ? []
                : variants.map((v) => ({
                    id: v.id,
                    name: v.name,
                    description: v.description,
                    sku: v.sku,
                    price: v.price,
                    image: v.newImageFile,
                    remove_image: v.removeImage,
                })),
            cooking_style_ids: isPerKilo ? cookingStyleIds : [],
            default_variant_index: isPerKilo ? null : defaultIndex,
            images: newImages.map((f) => f.file),
            remove_images: removedImageIds,
            primary_image_id: primaryImageId,
            // PHP never populates $_FILES/parses a multipart body for a
            // literal PUT request (only POST) — Inertia doesn't spoof this
            // automatically, so an edit with files must go out as a POST
            // carrying _method=put, exactly like Blade's @method('PUT').
            ...(isEdit ? { _method: 'put' } : {}),
        }));

        const options = { forceFormData: true, onSuccess: () => clearDraft(draftKey) };

        if (isEdit) {
            post(route('menu-items.update', item.id), options);
        } else {
            post(route('menu-items.store'), options);
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

                        <div className="mb-5">
                            <span className="block text-sm font-medium text-gray-700 mb-1.5">{t('Pricing Type')}</span>
                            <div className="inline-flex rounded-lg border border-[#D9CCBA] p-1 bg-[#FAF6EE]">
                                {[
                                    { value: 'fixed', label: t('Fixed Price') },
                                    { value: 'per_kilo', label: t('Per Kilo (Weighed)') },
                                ].map((option) => (
                                    <button
                                        key={option.value}
                                        type="button"
                                        onClick={() => setData('pricing_type', option.value)}
                                        className={`px-4 py-1.5 text-sm font-medium rounded-md transition ${
                                            data.pricing_type === option.value
                                                ? 'bg-[#8A3330] text-white shadow-sm'
                                                : 'text-gray-600 hover:text-gray-900'
                                        }`}
                                    >
                                        {option.label}
                                    </button>
                                ))}
                            </div>
                            {isPerKilo && (
                                <p className="mt-2 text-xs text-gray-500">
                                    {t('Priced from the scale. The final charge follows the actual weight taken at the counter.')}
                                </p>
                            )}
                            {errors.pricing_type && <p className="text-sm text-red-600 mt-2">{errors.pricing_type}</p>}
                        </div>

                        {isPerKilo && (
                            <div className="mb-5 grid grid-cols-1 sm:grid-cols-3 gap-5">
                                <div>
                                    <label htmlFor="price_per_kilo" className="block text-sm font-medium text-gray-700">{t('Price per Kilo (₱)')}</label>
                                    <input
                                        id="price_per_kilo"
                                        type="number"
                                        step="0.01"
                                        min="10"
                                        max="10000"
                                        value={data.price_per_kilo}
                                        onChange={(e) => setData('price_per_kilo', e.target.value)}
                                        required
                                        className="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm"
                                    />
                                    <p className="mt-1 text-xs text-gray-400">{t('Between ₱10 and ₱10,000 per kilo.')}</p>
                                    {errors.price_per_kilo && <p className="text-sm text-red-600 mt-2">{errors.price_per_kilo}</p>}
                                </div>
                                <div>
                                    <label htmlFor="min_weight_grams" className="block text-sm font-medium text-gray-700">{t('Minimum Weight (g)')}</label>
                                    <input
                                        id="min_weight_grams"
                                        type="number"
                                        min="50"
                                        value={data.min_weight_grams}
                                        onChange={(e) => setData('min_weight_grams', e.target.value)}
                                        className="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm"
                                    />
                                    <p className="mt-1 text-xs text-gray-400">{t('Smallest portion you will sell. At least 50 g.')}</p>
                                    {errors.min_weight_grams && <p className="text-sm text-red-600 mt-2">{errors.min_weight_grams}</p>}
                                </div>
                                <div>
                                    <label htmlFor="weight_step_grams" className="block text-sm font-medium text-gray-700">{t('Weight Step (g)')}</label>
                                    <input
                                        id="weight_step_grams"
                                        type="number"
                                        min="1"
                                        value={data.weight_step_grams}
                                        onChange={(e) => setData('weight_step_grams', e.target.value)}
                                        className="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm"
                                    />
                                    <p className="mt-1 text-xs text-gray-400">{t('Increment the scale entry snaps to.')}</p>
                                    {errors.weight_step_grams && <p className="text-sm text-red-600 mt-2">{errors.weight_step_grams}</p>}
                                </div>
                            </div>
                        )}

                        {isPerKilo && (
                            <div className="mb-5 flex flex-col gap-3">
                                <label className="inline-flex items-start gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.allow_tare}
                                        onChange={(e) => setData('allow_tare', e.target.checked)}
                                        className="mt-0.5 rounded border-gray-300 text-[#8A3330] shadow-sm focus:ring-[#8A3330]"
                                    />
                                    <span className="text-sm text-gray-600">
                                        {t('Allow Tare')}
                                        <span className="block text-xs text-gray-400">{t('Staff may deduct the weight of the container or ice before pricing.')}</span>
                                    </span>
                                </label>
                                <label className="inline-flex items-start gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.counter_only}
                                        onChange={(e) => setData('counter_only', e.target.checked)}
                                        className="mt-0.5 rounded border-gray-300 text-[#8A3330] shadow-sm focus:ring-[#8A3330]"
                                    />
                                    <span className="text-sm text-gray-600">
                                        {t('Counter Only')}
                                        <span className="block text-xs text-gray-400">{t('Cannot be ordered directly from the customer QR menu — staff weigh and add it at the counter.')}</span>
                                    </span>
                                </label>
                            </div>
                        )}

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
                            {!isPerKilo && (
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
                            )}
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

                    {isPerKilo ? (
                        <section className="bg-white border border-[#E5DDD0] rounded-xl p-6">
                            <h3 className="text-base font-semibold text-gray-900">{t('Cooking Styles')}</h3>
                            <p className="text-sm text-gray-500 mt-1 mb-4">
                                {t('How the customer can have this cooked. Pick every style your kitchen will accept for this item — the customer chooses one when ordering.')}
                            </p>

                            {cookingStyles.length === 0 ? (
                                <p className="text-sm text-gray-400">
                                    {t('No cooking styles have been set up yet.')}
                                </p>
                            ) : (
                                <div className="flex flex-wrap gap-2">
                                    {cookingStyles.map((style) => {
                                        const selected = cookingStyleIds.includes(style.id);

                                        return (
                                            <button
                                                key={style.id}
                                                type="button"
                                                onClick={() => toggleCookingStyle(style.id)}
                                                aria-pressed={selected}
                                                className={`px-3 py-1.5 rounded-full border text-sm font-medium transition ${
                                                    selected
                                                        ? 'border-[#8A3330] bg-[#8A3330] text-white shadow-sm'
                                                        : 'border-[#D9CCBA] text-gray-600 hover:border-[#8A3330]'
                                                }`}
                                            >
                                                {style.name}
                                                {style.surcharge > 0 && (
                                                    <span className={selected ? 'ml-1 text-white/80' : 'ml-1 text-gray-400'}>
                                                        +₱{style.surcharge.toFixed(2)}
                                                    </span>
                                                )}
                                            </button>
                                        );
                                    })}
                                </div>
                            )}

                            <p className="mt-3 text-xs text-gray-400">
                                {t('Variants (Solo/Large) do not apply to per-kilo items — the size is whatever the scale says.')}
                            </p>
                            {errors.cooking_style_ids && <p className="text-sm text-red-600 mt-2">{errors.cooking_style_ids}</p>}
                        </section>
                    ) : (
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
                    )}
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
