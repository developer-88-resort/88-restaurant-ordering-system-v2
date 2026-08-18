@props(['range', 'selectedMonth' => null, 'selectedDate' => null, 'calendarMonth'])

{{--
    The Today/This Week/This Month/All Time pill row + pick-a-date calendar
    popover, shared by /superadmin/reports and /superadmin/audit-logs so
    both pages offer the identical control rather than two hand-built
    copies. Must be placed inside a <form method="GET"> — it drives three
    hidden inputs (range/month/date) and submits that form on every
    selection, so whatever else is in the form (search, filters, ...)
    travels along with it instead of being wiped out.
--}}
<div
    x-data="{
        open: false,
        viewYear: {{ (int) explode('-', $calendarMonth)[0] }},
        viewMonth: {{ (int) explode('-', $calendarMonth)[1] - 1 }},
        selectedDate: {{ $selectedDate ? "'".$selectedDate."'" : 'null' }},
        weekdays: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
        pad(n) { return n === null ? '' : n.toString().padStart(2, '0'); },
        get monthLabel() {
            return new Date(this.viewYear, this.viewMonth, 1).toLocaleString('en-US', { month: 'long', year: 'numeric' });
        },
        get daysGrid() {
            const firstDay = new Date(this.viewYear, this.viewMonth, 1).getDay();
            const daysInMonth = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();
            const cells = [];
            for (let i = 0; i < firstDay; i++) cells.push(null);
            for (let d = 1; d <= daysInMonth; d++) cells.push(d);
            return cells;
        },
        prevMonth() { this.viewMonth--; if (this.viewMonth < 0) { this.viewMonth = 11; this.viewYear--; } },
        nextMonth() { this.viewMonth++; if (this.viewMonth > 11) { this.viewMonth = 0; this.viewYear++; } },
        dateKey(day) { return day === null ? '' : this.viewYear + '-' + this.pad(this.viewMonth + 1) + '-' + this.pad(day); },
        isToday(day) {
            const t = new Date();
            return t.getFullYear() === this.viewYear && t.getMonth() === this.viewMonth && t.getDate() === day;
        },
        isFuture(day) {
            const t = new Date(); t.setHours(0, 0, 0, 0);
            return new Date(this.viewYear, this.viewMonth, day) > t;
        },
        isSelected(day) { return day !== null && this.selectedDate === this.dateKey(day); },
        get displayLabel() {
            if (!this.selectedDate) return @js(__('Pick a date'));
            const [y, m, d] = this.selectedDate.split('-').map(Number);
            return new Date(y, m - 1, d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        },
        submitWith(range, month, date) {
            this.$refs.rangeInput.value = range ?? '';
            this.$refs.monthInput.value = month ?? '';
            this.$refs.dateInput.value = date ?? '';
            this.$el.closest('form').requestSubmit();
        },
    }"
    class="contents"
>
    <input type="hidden" name="range" x-ref="rangeInput" value="{{ (! $selectedMonth && ! $selectedDate) ? $range : '' }}">
    <input type="hidden" name="month" x-ref="monthInput" value="{{ $selectedMonth }}">
    <input type="hidden" name="date" x-ref="dateInput" value="{{ $selectedDate }}">

    <div class="flex flex-wrap items-center gap-2">
        @foreach (['today' => __('Today'), 'week' => __('This Week'), 'month' => __('This Month'), 'all' => __('All Time')] as $value => $label)
            <button type="button" @click="submitWith('{{ $value }}', null, null)"
               class="rounded-full px-4 py-2 text-sm font-bold transition {{ (! $selectedMonth && ! $selectedDate && $range === $value) ? 'bg-[#8A3330] text-white shadow-[0_10px_20px_-14px_rgba(138,51,48,0.9)]' : 'bg-white text-[#6C5E57] border border-[#E5D9CC] hover:border-[#8A3330]/35 hover:bg-[#FAF3EE]' }}">
                {{ $label }}
            </button>
        @endforeach

        <div @click.outside="open = false" class="relative">
            <button type="button" @click="open = !open"
                    class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-bold transition"
                    :class="selectedDate ? 'border-[#8A3330] text-[#8A3330] bg-[#F3E1DC]' : 'border-[#E5D9CC] text-[#6C5E57] bg-white hover:border-[#8A3330]/35 hover:bg-[#FAF3EE]'">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                </svg>
                <span x-text="displayLabel"></span>
            </button>

            <div x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 class="absolute z-50 mt-2 w-72 rounded-2xl border border-[#E5DDD0] bg-white p-4 shadow-[0_24px_55px_-24px_rgba(45,27,23,0.6)]">
                <div class="mb-3 flex items-center justify-between">
                    <button type="button" @click="prevMonth()" class="rounded-lg p-1.5 text-[#9A8B84] hover:bg-[#FAF6EE] hover:text-[#8A3330]">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                    </button>
                    <span class="text-sm font-bold text-[#251C19]" x-text="monthLabel"></span>
                    <button type="button" @click="nextMonth()" class="rounded-lg p-1.5 text-[#9A8B84] hover:bg-[#FAF6EE] hover:text-[#8A3330]">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                </div>

                <div class="mb-1 grid grid-cols-7 gap-1 text-center text-[11px] font-bold text-[#B0A49E]">
                    <template x-for="wd in weekdays" :key="wd"><span x-text="wd"></span></template>
                </div>

                <div class="grid grid-cols-7 gap-y-1">
                    <template x-for="(day, idx) in daysGrid" :key="idx">
                        <div class="flex items-center justify-center">
                            <button x-show="day !== null" type="button"
                                    x-text="day"
                                    :disabled="isFuture(day)"
                                    @click="submitWith(null, null, dateKey(day))"
                                    class="h-8 w-8 rounded-full text-sm font-semibold transition"
                                    :class="{
                                        'bg-[#8A3330] text-white': isSelected(day),
                                        'ring-1 ring-[#8A3330] text-[#8A3330]': isToday(day) && !isSelected(day),
                                        'text-[#D8CDC3] cursor-not-allowed': isFuture(day),
                                        'text-[#463934] hover:bg-[#FAF6EE]': !isSelected(day) && !isToday(day) && !isFuture(day),
                                    }"
                            ></button>
                        </div>
                    </template>
                </div>

                <div class="mt-3 flex items-center justify-between border-t border-[#EEE6DC] pt-3">
                    <button type="button" @click="submitWith('month', null, null)" x-show="selectedDate"
                            class="text-xs font-semibold text-[#9A8B84] hover:text-[#463934]">
                        {{ __('Clear') }}
                    </button>
                    <button type="button"
                            @click="submitWith(null, viewYear + '-' + pad(viewMonth + 1), null)"
                            class="ml-auto text-xs font-bold text-[#8A3330] hover:underline">
                        {{ __('View Entire Month') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
