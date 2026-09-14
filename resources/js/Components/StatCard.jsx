const ACCENTS = {
    brand: 'bg-[#F3E1DC] text-[#8A3330]',
    green: 'bg-emerald-50 text-emerald-600',
    blue: 'bg-orange-50 text-orange-600',
    amber: 'bg-amber-50 text-amber-700',
    purple: 'bg-indigo-50 text-indigo-600',
    rose: 'bg-rose-50 text-rose-500',
    slate: 'bg-slate-100 text-slate-700',
};

export default function StatCard({ icon, label, value, footnote, trend, accent = 'brand' }) {
    return (
        <div className="group relative min-h-[112px] overflow-hidden rounded-[18px] border border-[#ebe4dc] bg-white p-5 shadow-[0_10px_30px_-23px_rgba(55,35,30,0.48)] transition-all duration-200 hover:-translate-y-0.5 hover:border-[#d7c8bc] hover:shadow-[0_18px_38px_-24px_rgba(55,35,30,0.55)]">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="text-[10px] font-bold uppercase tracking-[0.15em] text-[#92847e]">{label}</div>
                    <div className="mt-1.5 truncate text-[25px] font-semibold leading-none tracking-[-0.035em] text-[#211b19]">
                        {value}
                    </div>
                </div>
                <div className={`grid h-14 w-14 shrink-0 place-items-center rounded-full border border-current/10 transition group-hover:scale-105 ${ACCENTS[accent] ?? ACCENTS.brand}`}>
                    {icon}
                </div>
            </div>

            {(footnote || trend != null) && (
                <p className="mt-2.5 truncate text-[11px] font-medium text-[#8e827c]">
                    {trend != null && (
                        <span className={trend >= 0 ? 'font-bold text-emerald-600' : 'font-bold text-red-600'}>
                            {trend >= 0 ? '+' : '-'}{Math.abs(trend)}%{' '}
                        </span>
                    )}
                    {footnote}
                </p>
            )}
        </div>
    );
}
