import { useTranslation } from '@/lib/i18n';
import { useId, useMemo, useRef } from 'react';
import { motion, useInView, useReducedMotion } from 'framer-motion';
import { Area, AreaChart, CartesianGrid, ReferenceLine, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const peso = (value) => `₱${Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const compactPeso = (value) => `₱${Intl.NumberFormat('en-PH', { notation: 'compact', maximumFractionDigits: 1 }).format(value)}`;
const dateLabel = (date) => new Date(`${date}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });

function SalesTooltip({ active, payload, t }) {
    if (!active || !payload?.length) return null;
    const day = payload[0].payload;

    return (
        <div className="rounded-2xl border border-[#E5DDD0]/70 bg-white/95 px-4 py-3 shadow-[0_12px_35px_-10px_rgba(37,28,25,0.2)] backdrop-blur-sm">
            <p className="text-xs text-slate-500">{day.label}{day.date ? ` · ${dateLabel(day.date)}` : ''}</p>
            <p className="mt-1 text-lg font-bold tabular-nums text-[#251C19]">{peso(day.total)}</p>
            <p className="mt-0.5 text-[11px] text-slate-500">{t('Paid orders only')}</p>
        </div>
    );
}

export default function SalesChart({ salesTrend = [] }) {
    const t = useTranslation();
    const chartRef = useRef(null);
    const isVisible = useInView(chartRef, { once: true, amount: 0.2 });
    const reduceMotion = useReducedMotion();
    const fillId = useId().replace(/:/g, '');
    const data = useMemo(() => salesTrend.map((day) => ({ ...day, total: Number(day.total) || 0 })), [salesTrend]);
    const total = data.reduce((sum, day) => sum + day.total, 0);
    const average = data.length ? total / data.length : 0;
    const best = data.reduce((highest, day) => day.total > (highest?.total ?? 0) ? day : highest, null);
    const hasSales = data.some((day) => day.total > 0);
    const range = data[0]?.date && data.at(-1)?.date
        ? `${dateLabel(data[0].date)} – ${dateLabel(data.at(-1).date)}`
        : t('Last 7 days');

    return (
        <motion.section
            ref={chartRef}
            initial={reduceMotion ? false : { opacity: 0, y: 14 }}
            animate={isVisible || reduceMotion ? { opacity: 1, y: 0 } : undefined}
            transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
            aria-label={t('Sales overview')}
            className="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white lg:col-span-2"
        >
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 pb-2 pt-5 sm:px-6">
                <div className="flex items-center gap-3">
                    <span aria-hidden="true" className="grid h-10 w-10 place-items-center rounded-xl bg-[#F7EEEA] text-[#8A3330]">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-5 w-5">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M3 17l5-5 4 3 8-10M15 5h5v5" />
                        </svg>
                    </span>
                    <div>
                        <h3 className="text-sm font-bold text-[#251C19]">{t('Sales overview')}</h3>
                        <p className="mt-0.5 text-xs text-slate-500">{t('Daily revenue')} · {t('Paid orders only')}</p>
                    </div>
                </div>
                <div className="flex items-center gap-2 rounded-full bg-[#F7F4EF] p-1 pr-3 text-[11px] font-medium text-slate-500">
                    <span className="rounded-full bg-[#8A3330] px-3 py-1.5 font-bold text-white">{t('7 days')}</span>
                    <span>{range}</span>
                </div>
            </div>

            <div className="grid grid-cols-2 gap-4 px-5 py-5 sm:grid-cols-[1.3fr_1fr_1fr] sm:px-6">
                <div className="col-span-2 sm:col-span-1">
                    <p className="text-xs font-medium text-slate-500">{t('Total sales')} <span className="text-[#9A8B84]">· {t('Last 7 days')}</span></p>
                    <p className="mt-1.5 break-words text-3xl font-bold tracking-tight tabular-nums text-[#251C19]">{peso(total)}</p>
                </div>
                <div className="sm:border-l sm:border-[#EEE5DC] sm:pl-5">
                    <p className="text-xs font-medium text-slate-500">{t('Daily average')}</p>
                    <p className="mt-2 break-words text-lg font-bold tabular-nums text-[#251C19]">{peso(average)}</p>
                </div>
                <div className="border-l border-[#EEE5DC] pl-5">
                    <p className="text-xs font-medium text-slate-500">{t('Best day')}</p>
                    <p className="mt-2 text-lg font-bold text-[#8A3330]">{best ? best.label : '—'} <span className="text-xs font-normal text-slate-500">{best?.date ? dateLabel(best.date) : ''}</span></p>
                    {best && <p className="mt-0.5 text-xs tabular-nums text-slate-500">{peso(best.total)}</p>}
                </div>
            </div>

            <div className="relative mx-3 mb-3 mt-2 h-60 sm:mx-5 sm:h-64">
                <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={data} margin={{ top: 16, right: 12, left: 0, bottom: 0 }} accessibilityLayer>
                        <defs>
                            <linearGradient id={fillId} x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stopColor="#B85851" stopOpacity={0.2} />
                                <stop offset="70%" stopColor="#B85851" stopOpacity={0.05} />
                                <stop offset="100%" stopColor="#B85851" stopOpacity={0} />
                            </linearGradient>
                        </defs>
                        <CartesianGrid vertical={false} stroke="#F0EDE9" />
                        <XAxis dataKey="label" padding={{ left: 8, right: 8 }} axisLine={false} tickLine={false} interval={0} tickMargin={12} height={36} tick={{ fontSize: 11, fill: '#7A6D66' }} />
                        <YAxis tickFormatter={compactPeso} axisLine={false} tickLine={false} width={62} tickMargin={8} tickCount={4} domain={[0, hasSales ? 'auto' : 1000]} tick={{ fontSize: 10, fill: '#8A7B74' }} />
                        <Tooltip content={<SalesTooltip t={t} />} cursor={{ stroke: '#BDAAA3', strokeWidth: 1, strokeDasharray: '4 5' }} />
                        {hasSales && <ReferenceLine y={average} stroke="#439B95" strokeWidth={1.5} strokeDasharray="5 6" />}
                        {(isVisible || reduceMotion) && <Area
                            type="monotone"
                            dataKey="total"
                            name={t('Sales')}
                            stroke="#A4443E"
                            strokeWidth={2.5}
                            strokeLinecap="round"
                            fill={`url(#${fillId})`}
                            dot={{ r: 3.5, fill: '#A4443E', stroke: '#fff', strokeWidth: 2 }}
                            activeDot={{ r: 6, fill: '#A4443E', stroke: '#F5DDD7', strokeWidth: 6 }}
                            isAnimationActive={!reduceMotion}
                            animationBegin={150}
                            animationDuration={1400}
                            animationEasing="ease-in-out"
                        />}
                    </AreaChart>
                </ResponsiveContainer>
                {!hasSales && (
                    <div className="pointer-events-none absolute inset-0 flex items-center justify-center pb-8 pl-12">
                        <div className="rounded-xl border border-[#EEE5DC] bg-white/95 px-5 py-3 text-center shadow-sm">
                            <p className="text-sm font-semibold text-[#6C5E57]">{t('No sales yet')}</p>
                            <p className="mt-1 text-xs text-slate-500">{t('Paid orders will appear here.')}</p>
                        </div>
                    </div>
                )}
            </div>
            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-[#F0EAE3]/70 px-5 py-4 text-[11px] text-slate-500 sm:px-6">
                <div className="flex items-center gap-4">
                    <span className="inline-flex items-center gap-2"><span className="h-0.5 w-4 rounded-full bg-[#A4443E]" />{t('Daily sales')}</span>
                    <span className="inline-flex items-center gap-2"><span className="w-4 border-t-2 border-dashed border-[#439B95]" />{t('Daily average')}</span>
                </div>
                <span>{t('All amounts in PHP')}</span>
            </div>
        </motion.section>
    );
}
