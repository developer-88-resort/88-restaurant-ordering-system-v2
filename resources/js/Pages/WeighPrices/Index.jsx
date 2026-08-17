import EmptyState from '@/Components/EmptyState';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const peso = (value) =>
    `₱${Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export default function Index({ date, today, isEditable, items }) {
    const t = useTranslation();

    // Keyed by item id so a blank input stays blank (meaning "leave this
    // one alone") rather than collapsing to 0.
    const [prices, setPrices] = useState(() =>
        Object.fromEntries(items.map((item) => [item.id, item.price ?? ''])),
    );

    const [processing, setProcessing] = useState(false);

    const setPrice = (id, value) => setPrices((current) => ({ ...current, [id]: value }));

    const copyFromYesterday = () => {
        setPrices(Object.fromEntries(items.map((item) => [item.id, item.yesterday_price.toFixed(2)])));
    };

    const changeDate = (value) => {
        router.get(route('weigh.prices.index'), { date: value }, { preserveState: false });
    };

    const save = (e) => {
        e.preventDefault();

        router.post(
            route('weigh.prices.store'),
            {
                date,
                prices: items.map((item) => ({
                    menu_item_id: item.id,
                    price_per_kilo: prices[item.id] === '' ? null : prices[item.id],
                })),
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title={t('Daily Market Prices')} />

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">{t('Daily Market Prices')}</h1>
                    <p className="text-sm text-gray-500 mt-1 max-w-2xl">
                        {t('Set the per-kilo rate for each weighed item once a day. This is the default price and the basis for comparison — a reason is required and logged whenever what is charged is different.')}
                    </p>
                </div>

                <div className="shrink-0">
                    <label htmlFor="date" className="block text-xs font-medium text-gray-500 mb-1">
                        {t('Date')}
                    </label>
                    <input
                        id="date"
                        type="date"
                        value={date}
                        max={today}
                        onChange={(e) => changeDate(e.target.value)}
                        className="border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm"
                    />
                </div>
            </div>

            {!isEditable && (
                <div className="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                    {t('Viewing a past day. These prices are a record of what was actually charged and cannot be edited.')}
                </div>
            )}

            {items.length === 0 ? (
                <EmptyState
                    title={t('No per-kilo items yet')}
                    description={t('Set a menu item\'s Pricing Type to "Per Kilo (Weighed)" and it will appear here.')}
                />
            ) : (
                <form onSubmit={save}>
                    <div className="bg-white border border-[#E5DDD0] rounded-xl overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-[#E5DDD0]">
                                <thead className="bg-[#FAF6EE]">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">{t('Item')}</th>
                                        <th className="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">{t("Yesterday's Price")}</th>
                                        <th className="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">{t("Today's Price")}</th>
                                        <th className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">{t('Set By')}</th>
                                        <th className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">{t('Set At')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#E5DDD0]">
                                    {items.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-6 py-3">
                                                <span className="text-sm font-medium text-gray-900">{item.name}</span>
                                                {item.category_name && (
                                                    <span className="block text-xs text-gray-400">{item.category_name}</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-3 text-right text-sm text-gray-600">
                                                {peso(item.yesterday_price)}
                                                {!item.yesterday_was_set && (
                                                    <span className="block text-[10px] text-gray-400">{t('menu default')}</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-3 text-right">
                                                {isEditable ? (
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="10"
                                                        max="10000"
                                                        inputMode="decimal"
                                                        value={prices[item.id]}
                                                        onChange={(e) => setPrice(item.id, e.target.value)}
                                                        placeholder={item.default_price_per_kilo.toFixed(2)}
                                                        aria-label={`${t("Today's Price")} — ${item.name}`}
                                                        className="w-32 text-right border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm"
                                                    />
                                                ) : (
                                                    <span className="text-sm font-semibold text-gray-900">
                                                        {item.price === null ? peso(item.default_price_per_kilo) : peso(item.price)}
                                                    </span>
                                                )}
                                                {item.price === null && (
                                                    <span className="block mt-0.5 text-[10px] text-gray-400">{t('not set — using menu default')}</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-3 text-sm text-gray-600">{item.set_by ?? '—'}</td>
                                            <td className="px-6 py-3 text-sm text-gray-600">{item.set_at ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {isEditable && (
                        <div className="sticky bottom-0 z-10 -mx-4 sm:-mx-6 mt-6 border-t border-[#E5DDD0] bg-[#F7F0E3]/95 backdrop-blur px-4 sm:px-6 py-4 flex flex-wrap items-center justify-end gap-3">
                            <button
                                type="button"
                                onClick={copyFromYesterday}
                                className="text-sm font-medium text-[#8A3330] hover:underline"
                            >
                                {t('Copy from yesterday')}
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex items-center px-4 py-2 bg-[#8A3330] border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-[#742927] transition ease-in-out duration-150 disabled:opacity-70"
                            >
                                {t('Save Prices')}
                            </button>
                        </div>
                    )}
                </form>
            )}
        </AuthenticatedLayout>
    );
}
