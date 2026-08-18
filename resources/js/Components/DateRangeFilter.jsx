import { useEffect, useRef, useState } from 'react';

const WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
const RANGE_OPTIONS = [
    ['today', 'Today'],
    ['week', 'This Week'],
    ['month', 'This Month'],
    ['all', 'All Time'],
];

/**
 * React port of the Today/This Week/This Month/All Time pill row + a
 * pick-a-date calendar popover, matching resources/views/superadmin/reports/
 * index.blade.php's Alpine implementation visually (same colors/shapes) —
 * no shared component exists to import across the Blade/Inertia boundary,
 * so this is a fresh same-look build for React pages.
 *
 * onChange is called with exactly one of {range}, {month}, or {date} —
 * mirroring the server's precedence (date > month > range).
 */
export default function DateRangeFilter({ range, selectedMonth, selectedDate, calendarMonth, onChange }) {
    const [open, setOpen] = useState(false);
    const [viewYear, setViewYear] = useState(() => Number(calendarMonth.split('-')[0]));
    const [viewMonth, setViewMonth] = useState(() => Number(calendarMonth.split('-')[1]) - 1);
    const containerRef = useRef(null);

    useEffect(() => {
        const onClickOutside = (e) => {
            if (containerRef.current && !containerRef.current.contains(e.target)) setOpen(false);
        };
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    const pad = (n) => n.toString().padStart(2, '0');
    const monthLabel = new Date(viewYear, viewMonth, 1).toLocaleString('en-US', { month: 'long', year: 'numeric' });

    const daysGrid = () => {
        const firstDay = new Date(viewYear, viewMonth, 1).getDay();
        const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
        const cells = [];
        for (let i = 0; i < firstDay; i++) cells.push(null);
        for (let d = 1; d <= daysInMonth; d++) cells.push(d);
        return cells;
    };

    const prevMonth = () => {
        if (viewMonth === 0) {
            setViewMonth(11);
            setViewYear((y) => y - 1);
        } else {
            setViewMonth((m) => m - 1);
        }
    };

    const nextMonth = () => {
        if (viewMonth === 11) {
            setViewMonth(0);
            setViewYear((y) => y + 1);
        } else {
            setViewMonth((m) => m + 1);
        }
    };

    const dateKey = (day) => `${viewYear}-${pad(viewMonth + 1)}-${pad(day)}`;

    const isToday = (day) => {
        const t = new Date();
        return t.getFullYear() === viewYear && t.getMonth() === viewMonth && t.getDate() === day;
    };

    const isFuture = (day) => {
        const t = new Date();
        t.setHours(0, 0, 0, 0);
        return new Date(viewYear, viewMonth, day) > t;
    };

    const isSelected = (day) => day !== null && selectedDate === dateKey(day);

    const displayLabel = () => {
        if (!selectedDate) return 'Pick a date';
        const [y, m, d] = selectedDate.split('-').map(Number);
        return new Date(y, m - 1, d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    };

    return (
        <div className="mb-6 flex flex-wrap items-center gap-2">
            {RANGE_OPTIONS.map(([value, label]) => (
                <button
                    key={value}
                    type="button"
                    onClick={() => onChange({ range: value })}
                    className={`rounded-full px-4 py-2 text-sm font-bold transition ${
                        !selectedMonth && !selectedDate && range === value
                            ? 'bg-[#8A3330] text-white shadow-[0_10px_20px_-14px_rgba(138,51,48,0.9)]'
                            : 'bg-white text-[#6C5E57] border border-[#E5D9CC] hover:border-[#8A3330]/35 hover:bg-[#FAF3EE]'
                    }`}
                >
                    {label}
                </button>
            ))}

            <div ref={containerRef} className="relative">
                <button
                    type="button"
                    onClick={() => setOpen((o) => !o)}
                    className={`inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-bold transition ${
                        selectedDate
                            ? 'border-[#8A3330] text-[#8A3330] bg-[#F3E1DC]'
                            : 'border-[#E5D9CC] text-[#6C5E57] bg-white hover:border-[#8A3330]/35 hover:bg-[#FAF3EE]'
                    }`}
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor" className="h-4 w-4">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                    <span>{displayLabel()}</span>
                </button>

                {open && (
                    <div className="absolute z-50 mt-2 w-72 rounded-2xl border border-[#E5DDD0] bg-white p-4 shadow-[0_24px_55px_-24px_rgba(45,27,23,0.6)]">
                        <div className="mb-3 flex items-center justify-between">
                            <button type="button" onClick={prevMonth} className="rounded-lg p-1.5 text-[#9A8B84] hover:bg-[#FAF6EE] hover:text-[#8A3330]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-4 w-4">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                                </svg>
                            </button>
                            <span className="text-sm font-bold text-[#251C19]">{monthLabel}</span>
                            <button type="button" onClick={nextMonth} className="rounded-lg p-1.5 text-[#9A8B84] hover:bg-[#FAF6EE] hover:text-[#8A3330]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-4 w-4">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </button>
                        </div>

                        <div className="mb-1 grid grid-cols-7 gap-1 text-center text-[11px] font-bold text-[#B0A49E]">
                            {WEEKDAYS.map((wd) => (
                                <span key={wd}>{wd}</span>
                            ))}
                        </div>

                        <div className="grid grid-cols-7 gap-y-1">
                            {daysGrid().map((day, idx) => (
                                <div key={idx} className="flex items-center justify-center">
                                    {day !== null && (
                                        <button
                                            type="button"
                                            disabled={isFuture(day)}
                                            onClick={() => {
                                                onChange({ date: dateKey(day) });
                                                setOpen(false);
                                            }}
                                            className={`h-8 w-8 rounded-full text-sm font-semibold transition ${
                                                isSelected(day)
                                                    ? 'bg-[#8A3330] text-white'
                                                    : isToday(day)
                                                      ? 'ring-1 ring-[#8A3330] text-[#8A3330]'
                                                      : isFuture(day)
                                                        ? 'text-[#D8CDC3] cursor-not-allowed'
                                                        : 'text-[#463934] hover:bg-[#FAF6EE]'
                                            }`}
                                        >
                                            {day}
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>

                        <div className="mt-3 flex items-center justify-between border-t border-[#EEE6DC] pt-3">
                            {selectedDate && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        onChange({ range: 'month' });
                                        setOpen(false);
                                    }}
                                    className="text-xs font-semibold text-[#9A8B84] hover:text-[#463934]"
                                >
                                    Clear
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={() => {
                                    onChange({ month: `${viewYear}-${pad(viewMonth + 1)}` });
                                    setOpen(false);
                                }}
                                className="ml-auto text-xs font-bold text-[#8A3330] hover:underline"
                            >
                                View Entire Month
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
