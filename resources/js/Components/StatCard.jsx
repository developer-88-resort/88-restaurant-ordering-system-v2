export default function StatCard({ icon, label, value, footnote, delay = 0, trend }) {
    return (
        <div
            className="group bg-white border border-[#E5DDD0] rounded-xl p-5 shadow-sm hover:shadow-md hover:border-[#8A3330]/30 hover:-translate-y-0.5 transition-all duration-200 animate-fade-slide-up"
            style={{ animationDelay: `${delay}ms` }}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="font-mono text-[11px] uppercase tracking-wider text-[#8A7B9E]">{label}</div>
                    <div className="mt-2 text-2xl font-bold text-gray-900 truncate flex items-center gap-2">
                        {value}
                        {trend != null && (
                            <span className={`text-xs font-semibold rounded-full px-1.5 py-0.5 ${trend >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                {trend >= 0 ? '▲' : '▼'} {Math.abs(trend)}%
                            </span>
                        )}
                    </div>
                </div>
                <div className="h-10 w-10 rounded-lg bg-[#F3E1DC] text-[#8A3330] flex items-center justify-center shrink-0">
                    {icon}
                </div>
            </div>
            {footnote && <p className="mt-3 pt-3 border-t border-[#E5DDD0]/70 text-xs text-gray-400">{footnote}</p>}
        </div>
    );
}
