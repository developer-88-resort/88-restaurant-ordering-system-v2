const ACCENTS = {
    brand: 'bg-[#F3E1DC] text-[#8A3330]',
    green: 'bg-emerald-50 text-emerald-600',
    blue: 'bg-blue-50 text-blue-600',
    amber: 'bg-amber-50 text-amber-700',
    purple: 'bg-indigo-50 text-indigo-600',
    rose: 'bg-rose-50 text-rose-500',
    slate: 'bg-slate-100 text-slate-700',
};

export default function StatCard({ icon, label, value, footnote, trend, accent = 'brand', featured = false }) {
    return (
        <div className={`relative min-h-[156px] rounded-2xl border p-5 ${featured ? 'border-[#8A3330] bg-[#8A3330] text-white' : 'border-slate-200 bg-white text-slate-900'}`}>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className={`text-sm font-medium ${featured ? 'text-white/80' : 'text-slate-500'}`}>{label}</div>
                    <div className="mt-4 break-words text-3xl font-semibold leading-none tracking-tight tabular-nums">
                        {value}
                    </div>
                </div>
                <div aria-hidden="true" className={`grid h-10 w-10 shrink-0 place-items-center rounded-xl ${featured ? 'bg-white/15 text-white' : ACCENTS[accent] ?? ACCENTS.brand}`}>
                    {icon}
                </div>
            </div>

            {(footnote || trend != null) && (
                <p className={`mt-5 text-xs ${featured ? 'text-white/80' : 'text-slate-500'}`}>
                    {trend != null && (
                        <span className={featured ? 'mr-1 rounded bg-white/15 px-1.5 py-0.5 font-semibold text-white' : trend >= 0 ? 'font-bold text-emerald-600' : 'font-bold text-red-600'}>
                            {trend >= 0 ? '+' : '-'}{Math.abs(trend)}%{' '}
                        </span>
                    )}
                    {footnote}
                </p>
            )}
        </div>
    );
}
