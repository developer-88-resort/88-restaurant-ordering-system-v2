import CookingStyleGrid from '@/Components/CookingStyleGrid';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

/**
 * All the interactive state/handlers for one item, shared between the
 * desktop table row and the mobile card so the two layouts never drift
 * apart in behavior — only in markup.
 */
function useItemRowState(item) {
    const [overrideOpen, setOverrideOpen] = useState(false);
    const [overrideIds, setOverrideIds] = useState(item.override_style_ids);
    const [position, setPosition] = useState(item.weighed_sort_order ?? '');

    const setCookingStyleSet = (setId) => {
        router.patch(route('weigh.items.update', item.id), {
            cooking_style_set_id: setId || null,
        }, { preserveScroll: true });
    };

    const savePosition = () => {
        router.patch(route('weigh.items.update', item.id), {
            cooking_style_set_id: item.cooking_style_set_id,
            weighed_sort_order: position === '' ? null : Number(position),
        }, { preserveScroll: true });
    };

    const toggleOverrideStyle = (styleId) => {
        setOverrideIds((current) => (current.includes(styleId) ? current.filter((id) => id !== styleId) : [...current, styleId]));
    };

    const saveOverride = () => {
        router.patch(route('weigh.items.update', item.id), {
            cooking_style_set_id: item.cooking_style_set_id,
            cooking_style_ids: overrideIds,
        }, { preserveScroll: true, onSuccess: () => setOverrideOpen(false) });
    };

    return { overrideOpen, setOverrideOpen, overrideIds, setOverrideIds, position, setPosition, setCookingStyleSet, savePosition, toggleOverrideStyle, saveOverride };
}

function StatusBadge({ item, t }) {
    return item.ready ? (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">
            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
            {t('Ready')}
        </span>
    ) : (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-[11px] font-semibold text-red-700">
            <span className="h-1.5 w-1.5 rounded-full bg-red-500" />
            {item.reasons[0]?.label}
        </span>
    );
}

function ResolvedStyles({ item, t }) {
    return item.resolved_styles.length === 0 ? (
        <span className="text-slate-400">{t('None')}</span>
    ) : (
        <span className="flex flex-wrap gap-1.5">
            {item.resolved_styles.map((s) => (
                <span key={s.id} className="rounded-md border border-slate-200/60 bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600">{s.name}</span>
            ))}
        </span>
    );
}

function CookingStyleSetPicker({ item, cookingStyleSets, t, setCookingStyleSet, overrideIds, overrideOpen, setOverrideOpen, className = '' }) {
    return (
        <div className={className}>
            <select
                value={item.cooking_style_set_id ?? ''}
                onChange={(e) => setCookingStyleSet(e.target.value ? Number(e.target.value) : null)}
                className="block min-h-11 w-full rounded-lg border-slate-200 bg-white text-sm focus:border-slate-400 focus:ring-slate-300"
            >
                <option value="">{t('— No set —')}</option>
                {cookingStyleSets.map((set) => (
                    <option key={set.id} value={set.id}>{set.name}</option>
                ))}
            </select>
            <button
                type="button"
                onClick={() => setOverrideOpen((v) => !v)}
                className="mt-2 rounded text-xs font-medium text-slate-600 hover:underline"
            >
                {overrideIds.length > 0
                    ? t(':count style(s) overridden').replace(':count', overrideIds.length)
                    : t('Override for this item')}
            </button>
        </div>
    );
}

function OverridePanel({ item, cookingStyles, overrideIds, setOverrideIds, toggleOverrideStyle, saveOverride, setOverrideOpen, t, className = '' }) {
    return (
        <div className={className}>
            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                {t('Per-item override — replaces the assigned set\'s styles entirely for :name only').replace(':name', item.name)}
            </p>
            <CookingStyleGrid styles={cookingStyles} selectedIds={overrideIds} onToggle={toggleOverrideStyle} />
            <div className="mt-3 flex flex-wrap items-center gap-3">
                <button type="button" onClick={saveOverride} className="inline-flex min-h-10 items-center justify-center whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                    {t('Save override')}
                </button>
                {overrideIds.length > 0 && (
                    <button type="button" onClick={() => setOverrideIds([])} className="text-xs font-medium text-gray-500 hover:text-gray-700">
                        {t('Clear override (fall back to set)')}
                    </button>
                )}
                <button type="button" onClick={() => setOverrideOpen(false)} className="text-xs font-medium text-gray-500 hover:text-gray-700">
                    {t('Close')}
                </button>
            </div>
        </div>
    );
}

function ItemTableRow({ item, cookingStyleSets, cookingStyles }) {
    const t = useTranslation();
    const state = useItemRowState(item);
    const { overrideOpen, setOverrideOpen, overrideIds, setOverrideIds, position, setPosition, setCookingStyleSet, savePosition, toggleOverrideStyle, saveOverride } = state;

    return (
        <>
            <tr className="transition hover:bg-slate-50/70">
                <td className="px-5 py-5 align-top">
                    <p className="text-sm font-bold text-slate-900">{item.name}</p>
                    <p className="text-xs text-slate-500">{item.category_name}</p>
                </td>
                <td className="px-5 py-5 align-top text-sm text-slate-700">
                    ₱{item.price_per_kilo.toFixed(2)} / kg
                    <p className="text-xs text-slate-500">{t('min')} {item.min_weight_grams} g</p>
                    <label className="mt-1 flex items-center gap-1 text-[11px] text-slate-500">
                        {t('By the Kilo position')}
                        <input
                            type="number"
                            min="0"
                            value={position}
                            onChange={(e) => setPosition(e.target.value)}
                            onBlur={savePosition}
                            placeholder="—"
                            className="min-h-8 w-16 rounded-lg border-slate-200 py-1 text-xs focus:border-slate-400 focus:ring-slate-300"
                        />
                    </label>
                </td>
                <td className="px-5 py-5 align-top">
                    <CookingStyleSetPicker
                        item={item} cookingStyleSets={cookingStyleSets} t={t}
                        setCookingStyleSet={setCookingStyleSet} overrideIds={overrideIds}
                        overrideOpen={overrideOpen} setOverrideOpen={setOverrideOpen}
                        className="min-w-[13rem] max-w-[16rem]"
                    />
                </td>
                <td className="px-5 py-5 align-top text-sm text-slate-700">
                    <ResolvedStyles item={item} t={t} />
                </td>
                <td className="px-5 py-5 align-top">
                    <StatusBadge item={item} t={t} />
                </td>
                <td className="px-5 py-5 align-top text-right text-sm">
                    <Link href={item.edit_url} className="inline-flex min-h-10 items-center justify-center whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                        {t('Edit in catalog')}
                    </Link>
                </td>
            </tr>
            {overrideOpen && (
                <tr>
                    <td colSpan={6} className="bg-slate-50 px-4 py-4">
                        <OverridePanel
                            item={item} cookingStyles={cookingStyles} overrideIds={overrideIds}
                            setOverrideIds={setOverrideIds} toggleOverrideStyle={toggleOverrideStyle}
                            saveOverride={saveOverride} setOverrideOpen={setOverrideOpen} t={t}
                        />
                    </td>
                </tr>
            )}
        </>
    );
}

function ItemCard({ item, cookingStyleSets, cookingStyles }) {
    const t = useTranslation();
    const state = useItemRowState(item);
    const { overrideOpen, setOverrideOpen, overrideIds, setOverrideIds, position, setPosition, setCookingStyleSet, savePosition, toggleOverrideStyle, saveOverride } = state;

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-sm font-bold text-slate-900">{item.name}</p>
                    <p className="text-xs text-slate-500">{item.category_name}</p>
                </div>
                <StatusBadge item={item} t={t} />
            </div>

            <div className="mt-3 flex items-center justify-between gap-3 text-sm text-slate-700">
                <span>
                    ₱{item.price_per_kilo.toFixed(2)} / kg
                    <span className="block text-xs text-slate-500">{t('min')} {item.min_weight_grams} g</span>
                </span>
                <label className="flex items-center gap-1 text-[11px] text-slate-500">
                    {t('By the Kilo position')}
                    <input
                        type="number"
                        min="0"
                        value={position}
                        onChange={(e) => setPosition(e.target.value)}
                        onBlur={savePosition}
                        placeholder="—"
                        className="min-h-8 w-16 rounded-lg border-slate-200 py-1 text-xs focus:border-slate-400 focus:ring-slate-300"
                    />
                </label>
            </div>

            <div className="mt-3 border-t border-slate-100 pt-3">
                <p className="mb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{t('Cooking Style Set')}</p>
                <CookingStyleSetPicker
                    item={item} cookingStyleSets={cookingStyleSets} t={t}
                    setCookingStyleSet={setCookingStyleSet} overrideIds={overrideIds}
                    overrideOpen={overrideOpen} setOverrideOpen={setOverrideOpen}
                />
            </div>

            <div className="mt-3 border-t border-slate-100 pt-3">
                <p className="mb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{t('Offers')}</p>
                <ResolvedStyles item={item} t={t} />
            </div>

            <div className="mt-3 flex justify-end border-t border-slate-100 pt-3">
                <Link href={item.edit_url} className="inline-flex min-h-10 items-center justify-center whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                    {t('Edit in catalog')}
                </Link>
            </div>

            {overrideOpen && (
                <div className="mt-3 rounded-xl bg-slate-50 p-3">
                    <OverridePanel
                        item={item} cookingStyles={cookingStyles} overrideIds={overrideIds}
                        setOverrideIds={setOverrideIds} toggleOverrideStyle={toggleOverrideStyle}
                        saveOverride={saveOverride} setOverrideOpen={setOverrideOpen} t={t}
                    />
                </div>
            )}
        </div>
    );
}

export default function Index({ items, cookingStyleSets, cookingStyles }) {
    const t = useTranslation();

    return (
        <AuthenticatedLayout>
            <Head title={t('Weighted Items')} />

            <div className="mx-auto max-w-[1500px] space-y-6 pb-10">
                <div className="rounded-2xl border border-slate-200 bg-white px-6 py-6 shadow-sm">
                    <h1 className="text-2xl font-bold text-gray-900">{t('Weighted Items')}</h1>
                    <p className="mt-2 max-w-5xl text-sm leading-6 text-slate-500">
                        {t('Assign a Cooking Style Set to each per-kilo item, or override its styles individually. Everything else about the item — price, images, add-ons — is edited in the catalog.')}
                    </p>
                </div>

                {items.length === 0 ? (
                    <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p className="py-8 text-center text-sm text-slate-500">
                            {t('No per-kilo items yet. Create one from Menu Management → New Item → Per Kilo.')}
                        </p>
                    </section>
                ) : (
                    <>
                        {/* Mobile: stacked cards — a 6-column table has no honest way to
                            fit a narrow screen, so this is a genuinely different layout,
                            not a squeezed-down table. */}
                        <div className="space-y-3 md:hidden">
                            {items.map((item) => (
                                <ItemCard key={item.id} item={item} cookingStyleSets={cookingStyleSets} cookingStyles={cookingStyles} />
                            ))}
                        </div>

                        {/* Desktop/tablet: the full table. */}
                        <section className="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-100">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">{t('Item')}</th>
                                            <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">{t('Rate')}</th>
                                            <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">{t('Cooking Style Set')}</th>
                                            <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">{t('Offers')}</th>
                                            <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">{t('Status')}</th>
                                            <th className="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">{t('Actions')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 bg-white">
                                        {items.map((item) => (
                                            <ItemTableRow key={item.id} item={item} cookingStyleSets={cookingStyleSets} cookingStyles={cookingStyles} />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </>
                )}

                <Link href={route('weigh.cooking-styles.index')} className="inline-flex min-h-10 items-center justify-center whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                    {t('Manage Cooking Styles & Sets')} →
                </Link>
            </div>
        </AuthenticatedLayout>
    );
}
