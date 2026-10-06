import AddOnRow from '@/Components/AddOnRow';
import ImageUploader from '@/Components/ImageUploader';
import RichTextEditor from '@/Components/RichTextEditor';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import { clearDraft, readDraft, writeDraft } from '@/draft-persistence';
import { useEffect, useRef, useState } from 'react';

// New / Edit Massage Service — New Menu Item's layout (MenuItems/ItemForm.jsx)
// for a massage service: Basic Information with the same rich-text
// description, Pricing & Duration, photos, and Availability, with the same
// fixed Cancel / Create bar at the bottom.
const inputClass = 'block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm';

const PRESETS = [30, 45, 60, 90, 120];
const MAX_MINUTES = 600;

// "1 hr 30 mins", "45 mins", "2 hrs" — the same wording as
// MassageService::durationLabel() on the cards.
export function formatDuration(minutes, t) {
    if (!minutes) return t('No duration');
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    const parts = [];
    if (hours) parts.push(`${hours} ${hours === 1 ? t('hr') : t('hrs')}`);
    if (mins) parts.push(`${mins} ${mins === 1 ? t('min') : t('mins')}`);
    return parts.join(' ');
}

// One box of the picker: − / + buttons around a number that can also be
// typed (e.g. a 3- or 5-minute add-on that no step or quick pick lands on).
// Lives outside DurationPicker so the input keeps focus while typing.
function Stepper({ id, label, value, max, onType, onMinus, onPlus, minusDisabled, plusDisabled }) {
    return (
        <div>
            <label htmlFor={id} className="block text-xs font-medium text-gray-500">{label}</label>
            <div className="mt-1 inline-flex items-center rounded-lg border border-gray-300 bg-white focus-within:border-[#8A3330] focus-within:ring-1 focus-within:ring-[#8A3330]">
                <button type="button" onClick={onMinus} disabled={minusDisabled} aria-label={`- ${label}`} className="grid h-10 w-10 place-items-center text-lg font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-30">
                    −
                </button>
                <input
                    id={id}
                    type="number"
                    inputMode="numeric"
                    min="0"
                    max={max}
                    value={value}
                    onFocus={(e) => e.target.select()}
                    onClick={(e) => e.target.select()}
                    onChange={(e) => onType(e.target.value === '' ? 0 : Math.max(0, Math.min(max, parseInt(e.target.value, 10) || 0)))}
                    className="w-14 border-0 p-0 text-center text-base font-semibold tabular-nums text-gray-900 focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                />
                <button type="button" onClick={onPlus} disabled={plusDisabled} aria-label={`+ ${label}`} className="grid h-10 w-10 place-items-center text-lg font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-30">
                    +
                </button>
            </div>
        </div>
    );
}

// Set the length without typing minutes by hand: a quick pick, or hours and
// minutes with − / + (15-minute steps) — and any number can still be typed
// into either box for a custom length. Stored as minutes.
function DurationPicker({ minutes, onChange, t }) {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    const set = (value) => onChange(Math.min(MAX_MINUTES, Math.max(0, value)));

    const chip = (selected) =>
        `rounded-lg border px-3 py-2 text-sm font-medium transition ${
            selected ? 'border-[#8A3330] bg-[#8A3330] text-white shadow-sm' : 'border-[#D9CCBA] bg-[#FAF6EE] text-gray-700 hover:border-[#8A3330]/50'
        }`;

    return (
        <div>
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <span className="block text-sm font-medium text-gray-700">{t('Duration')}</span>
                <span className="text-sm font-semibold text-[#8A3330]">{formatDuration(minutes, t)}</span>
            </div>

            <div className="mt-2 flex flex-wrap gap-2">
                {PRESETS.map((preset) => (
                    <button key={preset} type="button" onClick={() => set(preset)} className={chip(minutes === preset)}>
                        {formatDuration(preset, t)}
                    </button>
                ))}
                <button type="button" onClick={() => set(0)} className={chip(minutes === 0)}>
                    {t('None')}
                </button>
            </div>

            <div className="mt-4 flex flex-wrap items-end gap-5">
                <Stepper
                    id="duration_hours"
                    label={t('Hours')}
                    value={hours}
                    max={Math.floor(MAX_MINUTES / 60)}
                    onType={(h) => set(h * 60 + mins)}
                    onMinus={() => set(minutes - 60)}
                    onPlus={() => set(minutes + 60)}
                    minusDisabled={hours === 0}
                    plusDisabled={minutes + 60 > MAX_MINUTES}
                />
                <Stepper
                    id="duration_minutes_part"
                    label={t('Minutes')}
                    value={mins}
                    max={59}
                    onType={(m) => set(hours * 60 + m)}
                    onMinus={() => set(minutes - (mins % 15 || 15))}
                    onPlus={() => set(minutes + (15 - (mins % 15)))}
                    minusDisabled={minutes === 0}
                    plusDisabled={minutes >= MAX_MINUTES}
                />
            </div>
            <p className="mt-2 text-xs text-gray-400">{t('Optional — tap a quick pick, use − / +, or type the hours and minutes (e.g. 5 mins).')}</p>
        </div>
    );
}

// One variant, laid out like Menu Management's VariantRow, with the
// variant's own length (e.g. "1 hr") instead of a SKU.
function VariantRow({ variant, index, isDefault, errors, onChange, onRemove, onSetDefault, t }) {
    const minutes = Number(variant.duration_minutes) || 0;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    const setMinutes = (value) => onChange(index, { duration_minutes: Math.min(MAX_MINUTES, Math.max(0, value)) });
    const label = 'block text-[9px] font-semibold text-gray-400 uppercase tracking-wide mb-0.5';
    const field = 'block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm';
    const isUnpriced = String(variant.name ?? '').trim() !== '' && (variant.price === '' || variant.price === null);

    return (
        <div className="mb-3 rounded-lg border border-[#E5DDD0] p-3">
            <div className="grid grid-cols-12 gap-2">
                <div className="col-span-1 flex flex-col items-center gap-0.5">
                    <input type="radio" checked={isDefault} onChange={() => onSetDefault(index)} className="mt-1" />
                    <span className="text-[8px] uppercase tracking-wide text-gray-400">{t('Default')}</span>
                </div>
                <div className="col-span-6">
                    <label className={label}>{t('Variant Name')}</label>
                    <input type="text" value={variant.name} onChange={(e) => onChange(index, { name: e.target.value })} placeholder={t('e.g. 1 hr, Couple, With Hot Stone')} className={field} />
                </div>
                <div className="col-span-4">
                    <label className={label}>{t('Price (optional)')}</label>
                    <input type="number" step="0.01" min="0" value={variant.price ?? ''} onChange={(e) => onChange(index, { price: e.target.value })} placeholder={t('No price')} className={field} />
                </div>
                <div className="col-span-1 flex justify-center pt-4">
                    <button type="button" onClick={() => onRemove(index)} title={t('Remove this variant')} className="text-gray-400 hover:text-red-600">
                        ✕
                    </button>
                </div>

                <div className="col-span-11 col-start-2 flex flex-wrap items-end gap-4">
                    <Stepper
                        id={`variant-${index}-hours`}
                        label={t('Hours')}
                        value={hours}
                        max={Math.floor(MAX_MINUTES / 60)}
                        onType={(h) => setMinutes(h * 60 + mins)}
                        onMinus={() => setMinutes(minutes - 60)}
                        onPlus={() => setMinutes(minutes + 60)}
                        minusDisabled={hours === 0}
                        plusDisabled={minutes + 60 > MAX_MINUTES}
                    />
                    <Stepper
                        id={`variant-${index}-minutes`}
                        label={t('Minutes')}
                        value={mins}
                        max={59}
                        onType={(m) => setMinutes(hours * 60 + m)}
                        onMinus={() => setMinutes(minutes - (mins % 15 || 15))}
                        onPlus={() => setMinutes(minutes + (15 - (mins % 15)))}
                        minusDisabled={minutes === 0}
                        plusDisabled={minutes >= MAX_MINUTES}
                    />
                    <span className="pb-2.5 text-sm font-semibold text-[#8A3330]">{formatDuration(minutes, t)}</span>
                </div>

                <div className="col-span-11 col-start-2">
                    <label className={label}>{t('Description (optional)')}</label>
                    <input type="text" value={variant.description} onChange={(e) => onChange(index, { description: e.target.value })} placeholder={t('e.g. what makes this variant different')} className={field} />
                </div>
                {isUnpriced && <p className="col-span-11 col-start-2 text-xs text-amber-700">{t('No price — this option shows on the service but cannot be ordered.')}</p>}
                {(errors.name || errors.price) && <p className="col-span-11 col-start-2 text-xs text-red-600">{errors.name || errors.price}</p>}
            </div>
        </div>
    );
}

export default function ServiceForm({ service, nextSortOrder }) {
    const t = useTranslation();
    const isEdit = Boolean(service);

    // Unsaved input survives a refresh: kept per user (draft-persistence) and
    // per service, and only restored while the saved service is unchanged —
    // a draft never overwrites a newer save. New photos can't be kept.
    const draftKey = `massage-service:${service?.id ?? 'new'}`;
    const baseline = JSON.stringify(service ?? null);
    const [draft] = useState(() => {
        const saved = readDraft(draftKey);
        if (!saved || saved.baseline !== baseline) return null;
        return saved;
    });
    const [draftRestored, setDraftRestored] = useState(Boolean(draft));

    const [removedImageIds, setRemovedImageIds] = useState(() => draft?.removedImageIds ?? []);
    const [primaryImageId, setPrimaryImageId] = useState(() => {
        if (draft && 'primaryImageId' in draft) return draft.primaryImageId;
        const primary = service?.images.find((i) => i.is_primary);
        return primary?.id ?? service?.images[0]?.id ?? null;
    });
    const [newImages, setNewImages] = useState([]);

    // Variants (30 mins / 1 hr ...) and add-ons (Hot Stone ...), kept and
    // sent the way Menu Management's item form does.
    const [variants, setVariants] = useState(() =>
        draft?.variants ?? (service?.variants ?? []).map((v) => ({ id: v.id, name: v.name, description: v.description ?? '', price: v.price ?? '', duration_minutes: v.duration_minutes ?? 0 })),
    );
    const [defaultIndex, setDefaultIndex] = useState(() => {
        if (draft && 'defaultIndex' in draft) return draft.defaultIndex;
        const idx = (service?.variants ?? []).findIndex((v) => v.is_default);
        return idx >= 0 ? idx : null;
    });
    const [addOns, setAddOns] = useState(() =>
        draft?.addOns ?? (service?.add_ons ?? []).map((a) => ({ id: a.id, name: a.name, description: a.description ?? '', price: a.price ?? '' })),
    );

    const addVariant = () => {
        setVariants((current) => [...current, { id: null, name: '', description: '', price: '', duration_minutes: 0 }]);
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
    const updateVariant = (index, changes) => setVariants((current) => current.map((v, i) => (i === index ? { ...v, ...changes } : v)));
    const addAddOn = () => setAddOns((current) => [...current, { id: null, name: '', description: '', price: '' }]);
    const removeAddOn = (index) => setAddOns((current) => current.filter((_, i) => i !== index));
    const updateAddOn = (index, changes) => setAddOns((current) => current.map((a, i) => (i === index ? { ...a, ...changes } : a)));
    const hasPricedVariant = variants.some((v) => String(v.name).trim() !== '' && v.price !== '' && v.price !== null);

    const { data, setData, errors, processing, transform, post } = useForm(draft?.fields ?? {
        name: service?.name ?? '',
        description: service?.description ?? '',
        price: service?.price ?? '',
        duration_minutes: service?.duration_minutes ?? '',
        is_available: service?.is_available ?? true,
        sort_order: service?.sort_order ?? nextSortOrder ?? 0,
    });

    // Saved on every change after the first render (which is either the
    // saved service or the draft just restored, so nothing to write).
    // A form put back the way it was saved keeps no draft.
    const firstRender = useRef(true);
    const draftSnapshot = () => ({ baseline, fields: data, variants, defaultIndex, addOns, removedImageIds, primaryImageId });
    const pristine = useRef(null);
    useEffect(() => {
        const current = JSON.stringify(draftSnapshot());
        if (firstRender.current) {
            firstRender.current = false;
            if (!draft) pristine.current = current;
            return;
        }
        if (current === pristine.current) clearDraft(draftKey);
        else writeDraft(draftKey, draftSnapshot());
    }, [data, variants, defaultIndex, addOns, removedImageIds, primaryImageId]);

    const discardDraft = () => {
        clearDraft(draftKey);
        window.location.reload();
    };

    // A rejected save can land below the fold on a long form.
    useEffect(() => {
        if (Object.keys(errors).length === 0) return;
        document.querySelector('.text-red-600')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, [errors]);

    const submit = (e) => {
        e.preventDefault();

        transform((formData) => ({
            ...formData,
            is_available: formData.is_available ? 1 : 0,
            variants: variants.map((v) => ({ id: v.id, name: v.name, description: v.description, price: v.price, duration_minutes: v.duration_minutes || '' })),
            default_variant_index: defaultIndex,
            add_ons: addOns.map((a) => ({ id: a.id, name: a.name, description: a.description, price: a.price })),
            images: newImages.map((f) => f.file),
            remove_images: removedImageIds,
            primary_image_id: primaryImageId,
            // Files only travel on a POST; an edit says PUT with _method.
            ...(isEdit ? { _method: 'put' } : {}),
        }));

        // A good save leaves by a full page load (Inertia::location), so the
        // draft goes now and comes back only if the save is turned down.
        clearDraft(draftKey);
        post(isEdit ? route('massage.services.update', service.id) : route('massage.services.store'), {
            forceFormData: true,
            onError: () => writeDraft(draftKey, draftSnapshot()),
        });
    };

    const title = isEdit ? t('Edit Massage Service') : t('New Massage Service');

    return (
        <AuthenticatedLayout>
            <Head title={title} />
            <h1 className="text-2xl font-bold text-gray-900 mb-6">{title}</h1>

            {draftRestored && (
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <span>{t('Your unsaved changes were brought back. Click Save to keep them.')}</span>
                    <span className="flex items-center gap-4">
                        <button type="button" onClick={discardDraft} className="font-semibold text-amber-900 underline hover:no-underline">
                            {t('Discard changes')}
                        </button>
                        <button type="button" onClick={() => setDraftRestored(false)} className="text-amber-700 hover:text-amber-900" aria-label={t('Close')}>
                            ×
                        </button>
                    </span>
                </div>
            )}

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
                                        maxLength={150}
                                        className={inputClass}
                                    />
                                    {errors.name && <p className="text-sm text-red-600 mt-2">{errors.name}</p>}
                                </div>
                                <div>
                                    <label htmlFor="category" className="block text-sm font-medium text-gray-700">{t('Category')}</label>
                                    <input id="category" type="text" value={t('MASSAGE')} disabled className={`${inputClass} bg-gray-50 text-gray-600`} />
                                </div>
                            </div>

                            <div className="mt-5">
                                <label htmlFor="description" className="block text-sm font-medium text-gray-700">{t('Description')}</label>
                                <RichTextEditor
                                    id="description"
                                    value={data.description}
                                    onChange={(html) => setData('description', html)}
                                    placeholder={t('Describe this massage — what it includes, pressure, or what makes it special…')}
                                    className="mt-1"
                                />
                                {errors.description && <p className="text-sm text-red-600 mt-2">{errors.description}</p>}
                            </div>
                        </section>

                        <section className="bg-white border border-[#E5DDD0] rounded-xl p-6">
                            <h3 className="text-base font-semibold text-gray-900 mb-4">{t('Pricing & Duration')}</h3>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label htmlFor="price" className="block text-sm font-medium text-gray-700">{t('Price')}</label>
                                    <input
                                        id="price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.price}
                                        onChange={(e) => setData('price', e.target.value)}
                                        required={!hasPricedVariant}
                                        className={inputClass}
                                    />
                                    <p className="mt-1 text-xs text-gray-400">{t('Required unless you add variants below.')}</p>
                                    {errors.price && <p className="text-sm text-red-600 mt-2">{errors.price}</p>}
                                </div>
                                <div className="sm:col-span-2">
                                    <DurationPicker
                                        minutes={Number(data.duration_minutes) || 0}
                                        onChange={(value) => setData('duration_minutes', value > 0 ? value : '')}
                                        t={t}
                                    />
                                    {errors.duration_minutes && <p className="text-sm text-red-600 mt-2">{errors.duration_minutes}</p>}
                                </div>
                            </div>
                        </section>

                        <section className="bg-white border border-[#E5DDD0] rounded-xl p-6">
                            <h3 className="text-base font-semibold text-gray-900">{t('Variants')}</h3>
                            <p className="text-sm text-gray-500 mt-1 mb-4">
                                {t('Optional — use for the same massage in different lengths or packages, e.g. 30 mins / 1 hr / 1 hr 30 mins, each with its own price. Leave empty if it is just sold at the Price above.')}
                            </p>

                            {variants.map((variant, index) => (
                                <VariantRow
                                    key={index}
                                    variant={variant}
                                    index={index}
                                    isDefault={defaultIndex === index}
                                    errors={{ name: errors[`variants.${index}.name`], price: errors[`variants.${index}.price`] }}
                                    onChange={updateVariant}
                                    onRemove={removeVariant}
                                    onSetDefault={setDefaultIndex}
                                    t={t}
                                />
                            ))}

                            {variants.length > 0 && (
                                <p className="text-xs text-gray-400 mb-2">
                                    {t('"Default" is pre-selected when this massage is ordered. Leave a price blank for an option that is listed but not sold.')}
                                </p>
                            )}

                            <button type="button" onClick={addVariant} className="text-sm font-medium text-[#8A3330] hover:underline">
                                + {t('Add Variant')}
                            </button>
                        </section>

                        <section className="bg-white border border-[#E5DDD0] rounded-xl p-6">
                            <h3 className="text-base font-semibold text-gray-900">{t('Add-ons')}</h3>
                            <p className="text-sm text-gray-500 mt-1 mb-4">
                                {t('Optional extras the guest can add to this massage, each with their own quantity — e.g. "Hot Stone — ₱300".')}
                            </p>

                            {addOns.map((addOn, index) => (
                                <div key={index}>
                                    <AddOnRow
                                        addOn={addOn}
                                        index={index}
                                        onChange={updateAddOn}
                                        onRemove={removeAddOn}
                                        namePlaceholder={t('e.g. Hot Stone')}
                                        descriptionPlaceholder={t('e.g. warm basalt stones on the back')}
                                    />
                                    {errors[`add_ons.${index}.name`] && <p className="-mt-2 mb-3 text-xs text-red-600">{errors[`add_ons.${index}.name`]}</p>}
                                </div>
                            ))}

                            <button type="button" onClick={addAddOn} className="text-sm font-medium text-[#8A3330] hover:underline">
                                + {t('Add Add-on')}
                            </button>
                        </section>
                    </div>

                    <div className="space-y-6">
                        <section className="bg-white border border-[#E5DDD0] rounded-xl p-6">
                            <h3 className="text-base font-semibold text-gray-900">{t('Images')}</h3>
                            <p className="text-sm text-gray-500 mt-1 mb-4">{t('First image (or whichever is Primary) shows on the service cards and the order screen.')}</p>
                            <ImageUploader
                                existingImages={service?.images ?? []}
                                primaryId={primaryImageId}
                                onPrimaryChange={setPrimaryImageId}
                                removedIds={removedImageIds}
                                onRemovedChange={setRemovedImageIds}
                                newFiles={newImages}
                                onNewFilesChange={setNewImages}
                                error={errors.images ?? errors['images.0']}
                            />
                        </section>

                        <section className="bg-white border border-[#E5DDD0] rounded-xl p-6">
                            <h3 className="text-base font-semibold text-gray-900 mb-4">{t('Availability & Flags')}</h3>
                            <div>
                                <label htmlFor="is_available" className="block text-sm font-medium text-gray-700">{t('Availability')}</label>
                                <select
                                    id="is_available"
                                    value={data.is_available ? '1' : '0'}
                                    onChange={(e) => setData('is_available', e.target.value === '1')}
                                    className={inputClass}
                                >
                                    <option value="1">{t('Available')}</option>
                                    <option value="0">{t('Unavailable')}</option>
                                </select>
                            </div>

                            <div className="mt-5">
                                <label htmlFor="sort_order" className="block text-sm font-medium text-gray-700">{t('Sort Order')}</label>
                                <input
                                    id="sort_order"
                                    type="number"
                                    min="0"
                                    value={data.sort_order}
                                    onChange={(e) => setData('sort_order', e.target.value)}
                                    className={inputClass}
                                />
                                <p className="mt-1 text-xs text-gray-400">{t('Auto-filled with the next number in the list.')}</p>
                                {errors.sort_order && <p className="text-sm text-red-600 mt-2">{errors.sort_order}</p>}
                            </div>
                        </section>
                    </div>
                </div>

                <div className="sticky bottom-0 z-10 -mx-4 sm:-mx-6 mt-6 border-t border-[#E5DDD0] bg-[#F7F0E3]/95 backdrop-blur px-4 sm:px-6 py-4 flex items-center justify-end space-x-3">
                    <a href={route('massage.services.index')} onClick={() => clearDraft(draftKey)} className="text-sm text-gray-600 hover:text-gray-900">
                        {t('Cancel')}
                    </a>
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center px-4 py-2 bg-[#8A3330] border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-[#742927] transition ease-in-out duration-150 disabled:opacity-70"
                    >
                        {isEdit ? t('Save Changes') : t('Create Service')}
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
