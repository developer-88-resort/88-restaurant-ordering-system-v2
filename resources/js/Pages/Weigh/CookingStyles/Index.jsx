import ConfirmDialog from '@/Components/ConfirmDialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

function useSetRowState(set) {
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    const toggleStatus = () => {
        router.patch(route('weigh.cooking-styles.toggle-status', set.id), {}, { preserveScroll: true });
    };

    const destroy = () => {
        router.delete(route('weigh.cooking-styles.destroy', set.id), { preserveScroll: true, onFinish: () => setConfirmingDelete(false) });
    };

    return { confirmingDelete, setConfirmingDelete, toggleStatus, destroy };
}

function SetStyleChips({ set, t }) {
    return (
        <span className="flex flex-wrap gap-1">
            {set.styles.length === 0 ? (
                <span className="text-[#B08A80]">{t('No styles yet')}</span>
            ) : (
                set.styles.map((s) => (
                    <span key={s.id} className="rounded-full bg-[#F4E7E2] px-2 py-0.5 text-[11px] font-medium text-[#8A3330]">{s.name}</span>
                ))
            )}
        </span>
    );
}

function SetStatusToggle({ set, t, toggleStatus }) {
    return (
        <button
            type="button"
            onClick={toggleStatus}
            className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-bold transition ${
                set.is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border-slate-200 bg-slate-100 text-slate-600 hover:bg-slate-200'
            }`}
        >
            <span className={`h-1.5 w-1.5 rounded-full ${set.is_active ? 'bg-emerald-500' : 'bg-slate-400'}`} />
            {set.is_active ? t('Active') : t('Inactive')}
        </button>
    );
}

function SetRow({ set }) {
    const t = useTranslation();
    const { confirmingDelete, setConfirmingDelete, toggleStatus, destroy } = useSetRowState(set);

    return (
        <tr className="transition hover:bg-[#FAF6EE]/70">
            <td className="px-4 py-3 align-top">
                <p className="text-sm font-bold text-[#2B211E]">{set.name}</p>
                {set.description && <p className="text-xs text-[#8E8079]">{set.description}</p>}
            </td>
            <td className="px-4 py-3 align-top text-sm">
                <SetStyleChips set={set} t={t} />
            </td>
            <td className="px-4 py-3 align-top text-sm text-[#6C5F58]">{set.menu_items_count}</td>
            <td className="px-4 py-3 align-top">
                <SetStatusToggle set={set} t={t} toggleStatus={toggleStatus} />
            </td>
            <td className="px-4 py-3 align-top text-right text-sm">
                <div className="flex items-center justify-end gap-4">
                    <Link href={route('weigh.cooking-styles.edit', set.id)} className="font-bold text-[#8A3330] hover:text-[#5f2120]">
                        {t('Edit')}
                    </Link>
                    <button type="button" onClick={() => setConfirmingDelete(true)} className="font-bold text-red-600 hover:text-red-800">
                        {t('Delete')}
                    </button>
                </div>
            </td>
            <ConfirmDialog
                show={confirmingDelete}
                title={t('Delete this cooking style set?')}
                message={t('Items using it will be flagged as needing setup again.')}
                confirmLabel={t('Delete')}
                onConfirm={destroy}
                onCancel={() => setConfirmingDelete(false)}
            />
        </tr>
    );
}

function SetCard({ set }) {
    const t = useTranslation();
    const { confirmingDelete, setConfirmingDelete, toggleStatus, destroy } = useSetRowState(set);

    return (
        <div className="rounded-2xl border border-[#E4D8CB] bg-white p-4 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-sm font-bold text-[#2B211E]">{set.name}</p>
                    {set.description && <p className="text-xs text-[#8E8079]">{set.description}</p>}
                </div>
                <SetStatusToggle set={set} t={t} toggleStatus={toggleStatus} />
            </div>

            <div className="mt-3 border-t border-[#EEE5DC] pt-3">
                <p className="mb-1 text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Styles')}</p>
                <SetStyleChips set={set} t={t} />
            </div>

            <div className="mt-3 flex items-center justify-between border-t border-[#EEE5DC] pt-3 text-sm">
                <span className="text-[#6C5F58]">{t('Items using it')}: <span className="font-bold text-[#2B211E]">{set.menu_items_count}</span></span>
                <div className="flex items-center gap-4">
                    <Link href={route('weigh.cooking-styles.edit', set.id)} className="font-bold text-[#8A3330] hover:text-[#5f2120]">
                        {t('Edit')}
                    </Link>
                    <button type="button" onClick={() => setConfirmingDelete(true)} className="font-bold text-red-600 hover:text-red-800">
                        {t('Delete')}
                    </button>
                </div>
            </div>

            <ConfirmDialog
                show={confirmingDelete}
                title={t('Delete this cooking style set?')}
                message={t('Items using it will be flagged as needing setup again.')}
                confirmLabel={t('Delete')}
                onConfirm={destroy}
                onCancel={() => setConfirmingDelete(false)}
            />
        </div>
    );
}

function useMasterStyleRowState(style) {
    const [editing, setEditing] = useState(false);
    const { data, setData, patch, processing } = useForm({
        name: style.name,
        surcharge: style.surcharge,
        sort_order: style.sort_order,
        is_active: style.is_active,
    });

    const save = (e) => {
        e.preventDefault();
        patch(route('weigh.cooking-styles.styles.update', style.id), { preserveScroll: true, onSuccess: () => setEditing(false) });
    };

    return { editing, setEditing, data, setData, save, processing };
}

function MasterStyleRow({ style }) {
    const t = useTranslation();
    const { editing, setEditing, data, setData, save, processing } = useMasterStyleRowState(style);

    if (!editing) {
        return (
            <tr className="transition hover:bg-[#FAF6EE]/70">
                <td className="px-4 py-2 text-sm font-medium text-[#2B211E]">{style.name}</td>
                <td className="px-4 py-2 text-sm text-[#6C5F58]">{style.surcharge > 0 ? `+₱${style.surcharge.toFixed(2)}` : '—'}</td>
                <td className="px-4 py-2 text-sm text-[#6C5F58]">{style.sort_order}</td>
                <td className="px-4 py-2">
                    <span className={`inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[10px] font-bold ${style.is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-600'}`}>
                        {style.is_active ? t('Active') : t('Inactive')}
                    </span>
                </td>
                <td className="px-4 py-2 text-right">
                    <button type="button" onClick={() => setEditing(true)} className="text-xs font-bold text-[#8A3330] hover:underline">
                        {t('Edit')}
                    </button>
                </td>
            </tr>
        );
    }

    return (
        <tr className="bg-[#FAF6EE]">
            <td className="px-4 py-2">
                <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="w-full rounded-md border-gray-300 text-sm focus:border-[#8A3330] focus:ring-[#8A3330]" />
            </td>
            <td className="px-4 py-2">
                <input type="number" step="0.01" min="0" value={data.surcharge} onChange={(e) => setData('surcharge', e.target.value)} className="w-24 rounded-md border-gray-300 text-sm focus:border-[#8A3330] focus:ring-[#8A3330]" />
            </td>
            <td className="px-4 py-2">
                <input type="number" min="0" value={data.sort_order} onChange={(e) => setData('sort_order', e.target.value)} className="w-16 rounded-md border-gray-300 text-sm focus:border-[#8A3330] focus:ring-[#8A3330]" />
            </td>
            <td className="px-4 py-2">
                <label className="flex items-center gap-1.5 text-xs">
                    <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="rounded border-gray-300 text-[#8A3330] focus:ring-[#8A3330]" />
                    {t('Active')}
                </label>
            </td>
            <td className="px-4 py-2 text-right">
                <div className="flex justify-end gap-2">
                    <button type="button" onClick={save} disabled={processing} className="text-xs font-bold text-[#8A3330] hover:underline">{t('Save')}</button>
                    <button type="button" onClick={() => setEditing(false)} className="text-xs font-medium text-gray-500 hover:underline">{t('Cancel')}</button>
                </div>
            </td>
        </tr>
    );
}

function MasterStyleCard({ style }) {
    const t = useTranslation();
    const { editing, setEditing, data, setData, save, processing } = useMasterStyleRowState(style);

    if (!editing) {
        return (
            <div className="flex items-center justify-between gap-3 rounded-xl border border-[#EEE5DC] bg-white px-4 py-3">
                <div className="min-w-0">
                    <p className="text-sm font-medium text-[#2B211E]">{style.name}</p>
                    <p className="text-xs text-[#8E8079]">
                        {style.surcharge > 0 ? `+₱${style.surcharge.toFixed(2)}` : t('No surcharge')} &middot; {t('Sort')} {style.sort_order}
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-3">
                    <span className={`inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[10px] font-bold ${style.is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-600'}`}>
                        {style.is_active ? t('Active') : t('Inactive')}
                    </span>
                    <button type="button" onClick={() => setEditing(true)} className="text-xs font-bold text-[#8A3330] hover:underline">
                        {t('Edit')}
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-2 rounded-xl border border-[#EEE5DC] bg-[#FAF6EE] px-4 py-3">
            <label className="block text-xs font-medium text-[#8E8079]">
                {t('Name')}
                <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-0.5 block w-full rounded-md border-gray-300 text-sm focus:border-[#8A3330] focus:ring-[#8A3330]" />
            </label>
            <div className="flex gap-3">
                <label className="block text-xs font-medium text-[#8E8079]">
                    {t('Surcharge')}
                    <input type="number" step="0.01" min="0" value={data.surcharge} onChange={(e) => setData('surcharge', e.target.value)} className="mt-0.5 block w-24 rounded-md border-gray-300 text-sm focus:border-[#8A3330] focus:ring-[#8A3330]" />
                </label>
                <label className="block text-xs font-medium text-[#8E8079]">
                    {t('Sort')}
                    <input type="number" min="0" value={data.sort_order} onChange={(e) => setData('sort_order', e.target.value)} className="mt-0.5 block w-16 rounded-md border-gray-300 text-sm focus:border-[#8A3330] focus:ring-[#8A3330]" />
                </label>
            </div>
            <label className="flex items-center gap-1.5 text-xs">
                <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="rounded border-gray-300 text-[#8A3330] focus:ring-[#8A3330]" />
                {t('Active')}
            </label>
            <div className="flex gap-3 pt-1">
                <button type="button" onClick={save} disabled={processing} className="text-xs font-bold text-[#8A3330] hover:underline">{t('Save')}</button>
                <button type="button" onClick={() => setEditing(false)} className="text-xs font-medium text-gray-500 hover:underline">{t('Cancel')}</button>
            </div>
        </div>
    );
}

function useAddStyleState() {
    const { data, setData, post, processing, reset } = useForm({ name: '', surcharge: '', sort_order: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('weigh.cooking-styles.styles.store'), { preserveScroll: true, onSuccess: () => reset() });
    };

    return { data, setData, submit, processing };
}

function AddStyleRow() {
    const t = useTranslation();
    const { data, setData, submit, processing } = useAddStyleState();

    return (
        <tr>
            <td className="px-4 py-2">
                <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder={t('e.g. Buttered')} className="w-full rounded-md border-gray-300 text-sm focus:border-[#8A3330] focus:ring-[#8A3330]" />
            </td>
            <td className="px-4 py-2">
                <input type="number" step="0.01" min="0" value={data.surcharge} onChange={(e) => setData('surcharge', e.target.value)} placeholder="0.00" className="w-24 rounded-md border-gray-300 text-sm focus:border-[#8A3330] focus:ring-[#8A3330]" />
            </td>
            <td className="px-4 py-2">
                <input type="number" min="0" value={data.sort_order} onChange={(e) => setData('sort_order', e.target.value)} placeholder="0" className="w-16 rounded-md border-gray-300 text-sm focus:border-[#8A3330] focus:ring-[#8A3330]" />
            </td>
            <td />
            <td className="px-4 py-2 text-right">
                <button type="button" onClick={submit} disabled={processing || !data.name} className="text-xs font-bold text-[#8A3330] hover:underline disabled:opacity-40">
                    + {t('Add')}
                </button>
            </td>
        </tr>
    );
}

function AddStyleCard() {
    const t = useTranslation();
    const { data, setData, submit, processing } = useAddStyleState();

    return (
        <div className="space-y-2 rounded-xl border border-dashed border-[#D9CCBA] px-4 py-3">
            <label className="block text-xs font-medium text-[#8E8079]">
                {t('Name')}
                <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder={t('e.g. Buttered')} className="mt-0.5 block w-full rounded-md border-gray-300 text-sm focus:border-[#8A3330] focus:ring-[#8A3330]" />
            </label>
            <div className="flex gap-3">
                <label className="block text-xs font-medium text-[#8E8079]">
                    {t('Surcharge')}
                    <input type="number" step="0.01" min="0" value={data.surcharge} onChange={(e) => setData('surcharge', e.target.value)} placeholder="0.00" className="mt-0.5 block w-24 rounded-md border-gray-300 text-sm focus:border-[#8A3330] focus:ring-[#8A3330]" />
                </label>
                <label className="block text-xs font-medium text-[#8E8079]">
                    {t('Sort')}
                    <input type="number" min="0" value={data.sort_order} onChange={(e) => setData('sort_order', e.target.value)} placeholder="0" className="mt-0.5 block w-16 rounded-md border-gray-300 text-sm focus:border-[#8A3330] focus:ring-[#8A3330]" />
                </label>
            </div>
            <button type="button" onClick={submit} disabled={processing || !data.name} className="text-xs font-bold text-[#8A3330] hover:underline disabled:opacity-40">
                + {t('Add')}
            </button>
        </div>
    );
}

export default function Index({ sets, styles }) {
    const t = useTranslation();

    return (
        <AuthenticatedLayout>
            <Head title={t('Cooking Styles')} />

            <div className="space-y-8 pb-10">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">{t('Cooking Styles')}</h1>
                        <p className="mt-1 text-sm text-[#8E8079]">
                            {t('Reusable sets of cooking styles, assigned to per-kilo items from Weighted Items.')}
                        </p>
                    </div>
                    <Link href={route('weigh.cooking-styles.create')} className="inline-flex items-center gap-2 rounded-xl bg-[#8A3330] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#742927]">
                        + {t('New Set')}
                    </Link>
                </div>

                <section className="rounded-2xl border border-[#E4D8CB] bg-white p-4 shadow-sm">
                    <h2 className="mb-3 text-sm font-black text-[#2B211E]">{t('Sets')}</h2>
                    {sets.length === 0 ? (
                        <p className="py-6 text-center text-sm text-[#8E8079]">{t('No cooking style sets yet.')}</p>
                    ) : (
                        <>
                            {/* Mobile: stacked cards — a 5-column table has no honest
                                way to fit a narrow screen. */}
                            <div className="space-y-3 md:hidden">
                                {sets.map((set) => <SetCard key={set.id} set={set} />)}
                            </div>

                            <div className="hidden overflow-x-auto rounded-xl border border-[#EEE5DC] md:block">
                                <table className="min-w-full divide-y divide-[#EEE5DC]">
                                    <thead className="bg-[#FAF6EE]">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Name')}</th>
                                            <th className="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Styles')}</th>
                                            <th className="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Items using it')}</th>
                                            <th className="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Status')}</th>
                                            <th className="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Actions')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[#EEE5DC] bg-white">
                                        {sets.map((set) => <SetRow key={set.id} set={set} />)}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </section>

                <section className="rounded-2xl border border-[#E4D8CB] bg-white p-4 shadow-sm">
                    <h2 className="mb-1 text-sm font-black text-[#2B211E]">{t('Master style list')}</h2>
                    <p className="mb-3 text-xs text-[#8E8079]">{t('Deactivate instead of deleting a style that has ever been used — order history keeps referencing it.')}</p>

                    {/* Mobile: stacked cards, same inline-edit behavior as the table rows. */}
                    <div className="space-y-2 md:hidden">
                        {styles.map((style) => <MasterStyleCard key={style.id} style={style} />)}
                        <AddStyleCard />
                    </div>

                    <div className="hidden overflow-x-auto rounded-xl border border-[#EEE5DC] md:block">
                        <table className="min-w-full divide-y divide-[#EEE5DC]">
                            <thead className="bg-[#FAF6EE]">
                                <tr>
                                    <th className="px-4 py-2 text-left text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Name')}</th>
                                    <th className="px-4 py-2 text-left text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Surcharge')}</th>
                                    <th className="px-4 py-2 text-left text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Sort')}</th>
                                    <th className="px-4 py-2 text-left text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Status')}</th>
                                    <th className="px-4 py-2 text-right text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#EEE5DC] bg-white">
                                {styles.map((style) => <MasterStyleRow key={style.id} style={style} />)}
                                <AddStyleRow />
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
