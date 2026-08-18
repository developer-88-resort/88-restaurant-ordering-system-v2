const ACCENTS = {
    brand: 'bg-[#F3E1DC] text-[#8A3330]',
    green: 'bg-emerald-50 text-emerald-700',
    blue: 'bg-blue-50 text-blue-700',
    amber: 'bg-amber-50 text-amber-700',
    purple: 'bg-violet-50 text-violet-700',
    rose: 'bg-rose-50 text-rose-700',
    slate: 'bg-slate-100 text-slate-700',
};

export default function StatCard({ icon, label, value, footnote, trend, accent = 'brand' }) {
    return (
        <div className="group relative overflow-hidden rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_14px_38px_-30px_rgba(55,35,30,0.55)] transition-all duration-200 hover:-translate-y-0.5 hover:border-[#CDB9A8] hover:shadow-[0_20px_45px_-28px_rgba(55,35,30,0.6)]">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{label}</div>
                    <div className="mt-2 flex min-w-0 items-center gap-2 truncate text-2xl font-black tracking-[-0.02em] text-[#251C19]">
                        {value}
                        {trend != null && (
                            <span className={`shrink-0 rounded-full px-1.5 py-0.5 text-xs font-bold ${trend >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}`}>
                                {trend >= 0 ? '▲' : '▼'} {Math.abs(trend)}%
                            </span>
                        )}
                    </div>
                </div>
                <div className={`grid h-11 w-11 shrink-0 place-items-center rounded-xl transition group-hover:scale-105 ${ACCENTS[accent] ?? ACCENTS.brand}`}>
                    {icon}
                </div>
            </div>
            {footnote && <p className="mt-3 border-t border-[#EEE6DC] pt-3 text-xs font-medium text-[#9A8B84]">{footnote}</p>}
        </div>
    );
}
