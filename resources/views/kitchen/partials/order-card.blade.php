@php
    $accentClasses = match ($accentColor ?? 'amber') {
        'blue' => ['button' => 'bg-blue-600 hover:bg-blue-700'],
        'purple' => ['button' => 'bg-purple-600 hover:bg-purple-700'],
        default => ['button' => 'bg-amber-600 hover:bg-amber-700'],
    };
    // One order can now hold more than one round at once (an earlier batch
    // already cooking, a later one just appended) — group by batch instead
    // of showing a single order-level batch number, and only bother with
    // the grouping headers when there's actually more than one to show.
    $itemsByBatch = $order->items->groupBy('batch_number');
    $hasMultipleBatches = $itemsByBatch->count() > 1;

    // Same for every line on this slip — see OrderItemPolicy.
    $cancelNeedsApproval = ! ($isManager ?? false) && \App\Services\OrderItemCanceller::requiresApproval($order);

    // A settled slip is closed to line edits until its payment is voided —
    // the invoice froze exactly these lines. OrderItemPolicy and
    // OrderItemCanceller refuse it server-side too; this only stops the
    // kitchen tapping a button that was always going to be rejected.
    $linesLocked = $order->payment_status === \App\Enums\PaymentStatus::Paid;
    $slipLabel = $order->orderNumber().' · '.($order->order_type === \App\Enums\OrderType::Takeout ? __('Take-out') : $order->slipLocationLabel());

    // Only a standalone advance order is labelled as one up here; an advance
    // order added to this slip is labelled on its own lines instead.
    $openingQuotation = $order->openingQuotation();

    // The table's other slips still on the board.
    $otherSlips = $order->space_session_id
        ? collect(($slipsByTab ?? collect())->get($order->space_session_id, []))->reject(fn ($other) => $other->is($order))->sortBy('slip_number')
        : collect();

    // What the printed slip will show — never the receipt's figures (no VAT,
    // a discount is a plain share of the price). See OrderSlipTotals.
    $slipTotals = \App\Services\Printing\OrderSlipTotals::for($order);
    $slipDiscountAction = \Illuminate\Support\Js::from([
        'url' => route('kitchen.slip-discounts.update', $order),
        'slipLabel' => $slipLabel,
        'subtotal' => (float) $slipTotals['subtotal'],
        'items' => $order->items->reject->isFullyCancelled()->map(fn ($item) => [
            'id' => $item->id,
            'name' => ($item->isWeighed() ? number_format((float) $item->netWeightGrams()).'g' : $item->activeQuantity().'×').' '.$item->item_name,
            'amount' => (float) $item->lineTotalNet(),
        ])->values(),
        'current' => collect($order->slip_discounts ?? [])->map(fn ($entry) => [
            'rule_id' => $entry['rule_id'],
            'value' => $entry['value'] ?? null,
            'item_ids' => $entry['item_ids'] ?? [],
            'eligible_amount' => $entry['eligible_amount'] ?? null,
            'qualified_name' => $entry['qualified_name'] ?? null,
        ])->values(),
    ]);
@endphp

<div class="overflow-hidden rounded-2xl bg-white border border-slate-200 shadow-sm">
    <div class="px-5 pt-4 pb-3">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <span class="text-base font-semibold tracking-tight text-slate-900">{{ $order->orderNumber() }}</span>
                <p class="text-sm text-gray-500 truncate">
                    @if ($order->order_type === \App\Enums\OrderType::Takeout)
                        {{ __('Take-out') }}
                    @else
                        {{ $order->locationLabel() }}
                        <span class="text-gray-300">&bull;</span>
                        {{ $order->order_type->label() }}
                    @endif
                </p>
                {{-- Which slip this is for the table, and when it came in — a
                     table can have several open at once. --}}
                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                    @if ($order->slip_number)
                        <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">{{ $order->slipLabel() }}</span>
                    @endif
                    <span class="text-xs text-gray-500">{{ __('Submitted') }} {{ $order->created_at->format('g:i A') }}</span>
                </p>
                @if ($otherSlips->isNotEmpty())
                    <p class="mt-1 text-[11px] font-medium text-slate-600">
                        {{ __('Also open for this table') }}:
                        {{ $otherSlips->map(fn ($other) => ($other->slipLabel() ?? $other->orderNumber()).' ('.$other->status->label().')')->implode(', ') }}
                    </p>
                @endif
                {{-- Its own line rather than appended to the location above,
                     which truncates — at lg the board is three columns wide
                     and a head count is not something the kitchen should have
                     to read off a cut-off line. --}}
                @if ($order->pax)
                    <p class="text-xs font-bold text-gray-800">{{ $order->pax }} {{ __('pax') }}</p>
                @endif
                @if ($order->guestSession)
                    <p class="text-xs font-semibold text-teal-700 truncate">{{ $order->guestSession->displayLabel() }}</p>
                @endif
                @if ($order->customer_name)
                    <p class="text-xs text-slate-600 font-medium truncate">{{ __('Ordered by') }}: {{ $order->customer_name }}</p>
                @endif
            </div>
            <div
                x-data="{
                    start: new Date('{{ $order->created_at->toIso8601String() }}').getTime(),
                    now: Date.now(),
                }"
                x-init="
                    const timer = setInterval(() => now = Date.now(), 1000);
                    turboCleanup(() => clearInterval(timer));
                "
                class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-medium tabular-nums text-slate-500 shrink-0"
                x-text="(() => {
                    const diff = Math.max(0, Math.floor((now - start) / 1000));
                    const m = Math.floor(diff / 60);
                    const s = diff % 60;
                    return m + ':' + String(s).padStart(2, '0');
                })()"
            ></div>
        </div>

        @if ($openingQuotation)
            <span class="mt-2 inline-flex items-center gap-1 rounded-full bg-amber-100 border border-amber-300 px-2.5 py-1 text-xs font-bold uppercase text-amber-800">
                {{ __('Advance Order') }} · {{ $openingQuotation->quotation_number }}
            </span>
        @endif
    </div>

    <div class="border-t border-slate-200"></div>

    <div class="px-5 py-4 space-y-3">
        @foreach ($itemsByBatch as $batchNumber => $batchItems)
            <div>
                @if ($hasMultipleBatches)
                    <p class="mb-1.5 text-[10px] font-bold uppercase tracking-wide text-teal-700">
                        {{ $batchNumber ? __('Batch') . ' #' . $batchNumber : __('Earlier round') }}
                    </p>
                @endif
                <div class="space-y-3">
                    @foreach ($batchItems as $item)
                        @php
                            $itemCancelled = $item->isFullyCancelled();
                            $activeQty = $item->activeQuantity();
                            $partlyCancelled = ! $itemCancelled && $item->cancelledQuantity() > 0;
                            $lineAction = fn (string $mode) => \Illuminate\Support\Js::from([
                                'mode' => $mode,
                                'url' => route('kitchen.items.cancel', [$order, $item]),
                                'name' => $item->item_name,
                                'activeQty' => $activeQty,
                                'isWeighed' => $item->isWeighed(),
                                'needsApproval' => $cancelNeedsApproval,
                                'slipLabel' => $slipLabel,
                            ]);
                        @endphp
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 text-sm leading-6 {{ $itemCancelled ? 'text-gray-400' : 'text-gray-800' }}">
                                {{-- A weighed line is one piece of food off the scale, so the
                                     kitchen needs the grams, not a "1×". --}}
                                @if ($item->isWeighed())
                                    <span class="font-semibold {{ $itemCancelled ? 'line-through' : '' }}">{{ number_format((float) $item->netWeightGrams()) }}g</span>
                                @elseif ($partlyCancelled)
                                    {{-- What's left to cook, with what was ordered struck beside it. --}}
                                    <span class="text-gray-400 line-through">{{ $item->quantity }}&times;</span>
                                    <span class="font-semibold">{{ $activeQty }}&times;</span>
                                @else
                                    <span class="font-semibold {{ $itemCancelled ? 'line-through' : '' }}">{{ $item->quantity }}&times;</span>
                                @endif
                                <span class="{{ $itemCancelled ? 'line-through' : '' }}">{{ $item->item_name }}</span>
                                @if ($item->isWeighed() && $item->cookingLabel())
                                    <span class="ml-1 inline-flex px-1.5 py-0.5 rounded bg-teal-50 text-teal-700 text-[10px] font-bold uppercase">{{ $item->cookingLabel() }}</span>
                                @endif
                                @if ($itemCancelled)
                                    <span class="ml-1 inline-flex px-1.5 py-0.5 rounded bg-red-100 text-red-700 text-[10px] font-bold uppercase">{{ $item->isWeighed() ? __('Voided') : __('Cancelled') }}</span>
                                @elseif ($partlyCancelled)
                                    <span class="ml-1 inline-flex px-1.5 py-0.5 rounded bg-red-100 text-red-700 text-[10px] font-bold uppercase">−{{ $item->cancelledQuantity() }} {{ __('cancelled') }}</span>
                                @endif
                                @if ($item->cooking_note)
                                    <div class="pl-5 text-xs text-teal-700">{{ $item->cooking_note }}</div>
                                @endif
                                @if ($item->notes)
                                    <div class="pl-5 text-xs text-gray-400">{{ $item->notes }}</div>
                                @endif
                                @foreach ($item->adjustments as $adjustment)
                                    <div class="pl-5 text-[11px] text-red-500">
                                        −{{ $adjustment->quantity }} · {{ $adjustment->reason_code->label() }}@if ($adjustment->notes) — {{ $adjustment->notes }}@endif
                                    </div>
                                @endforeach
                            </div>

                            @if ($linesLocked)
                                <span class="shrink-0 text-[11px] font-semibold text-gray-400" title="{{ __('Void the payment first to change this order.') }}">
                                    {{ __('Paid') }}
                                </span>
                            @elseif (! $itemCancelled)
                                <div class="flex shrink-0 items-center gap-1">
                                    @if (! $item->isWeighed() && $activeQty > 1)
                                        <button type="button"
                                                @click="$dispatch('kitchen-cancel-item', {{ $lineAction('adjust') }})"
                                                class="min-h-8 rounded-lg border border-slate-200 px-2 text-[11px] font-semibold text-gray-600 hover:border-slate-400 hover:text-slate-600"
                                                title="{{ __('Adjust Quantity') }}">
                                            {{ __('Qty') }}
                                        </button>
                                    @endif
                                    <button type="button"
                                            @click="$dispatch('kitchen-cancel-item', {{ $lineAction('cancel') }})"
                                            class="min-h-8 rounded-lg border border-red-200 px-2 text-[11px] font-semibold text-red-600 hover:bg-red-50"
                                            title="{{ $item->isWeighed() ? __('Void line') : __('Cancel Item') }}">
                                        {{ $item->isWeighed() ? __('Void') : __('Cancel') }}
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if ($order->notes)
            <div class="mt-2 text-xs bg-amber-50 text-amber-800 border border-amber-200 rounded-md px-2 py-1.5">
                {{ $order->notes }}
            </div>
        @endif

        {{-- The slip's total, and its discount picked before printing. --}}
        <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <div class="min-w-0 flex-1 text-xs">
                @if ($slipTotals['discounts'])
                    <div class="flex justify-between gap-2 text-gray-500">
                        <span>{{ __('Subtotal') }}</span>
                        <span>₱{{ number_format((float) $slipTotals['subtotal'], 2) }}</span>
                    </div>
                    @foreach ($slipTotals['discounts'] as $discount)
                        <div class="flex justify-between gap-2 text-slate-600">
                            <span class="truncate">{{ $discount['name'] }}@if ($discount['rate']) ({{ $discount['rate'] }})@endif</span>
                            <span class="shrink-0">−₱{{ number_format((float) $discount['amount'], 2) }}</span>
                        </div>
                    @endforeach
                @endif
                <div class="flex justify-between gap-2 font-semibold tabular-nums text-slate-900">
                    <span>{{ __('Slip Total') }}</span>
                    <span>₱{{ number_format((float) $slipTotals['total'], 2) }}</span>
                </div>
            </div>
            <button type="button"
                    @click="$dispatch('kitchen-slip-discount', {{ $slipDiscountAction }})"
                    class="min-h-8 shrink-0 rounded-lg border border-slate-200 bg-white px-2.5 text-[11px] font-semibold text-slate-600 hover:border-slate-400"
                    title="{{ __('Discount on the printed slip') }}">
                {{ __('Discount') }}
            </button>
        </div>
    </div>

    {{--
        A grid at every width rather than one flex row. The board goes to
        three columns at lg, which leaves a card only ~300px wide — not
        enough for four buttons side by side, so the row that looked fine on
        a 1920px screen overflowed on both a phone and a laptop. Here the
        primary action takes a full row, the two print actions share the
        next, and Cancel takes the last.
    --}}
    <div class="px-5 pb-5 grid grid-cols-2 gap-2">
        <form action="{{ route('orders.update-status', $order) }}" method="POST" class="col-span-2">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="{{ $nextStatus }}">
            <button type="submit" class="w-full {{ $accentClasses['button'] }} text-white text-sm font-semibold rounded-xl py-3 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
                {{ $buttonLabel }}
            </button>
        </form>

        <a
            href="{{ route('orders.kitchen-slip.print', $order) }}"
            data-turbo="false"
            class="inline-flex items-center justify-center px-4 py-3 border border-slate-200 text-gray-600 text-sm font-semibold rounded-lg hover:bg-gray-50 transition"
        >
            {{ __('Print') }}
        </a>

        {{-- One press, one slip: see resources/js/lib/kitchen-direct-print.js. --}}
        <div
            x-data="kitchenDirectPrint(@js([
                'queueUrl' => route('orders.kitchen-slip.print-thermal', $order),
                'statusUrl' => route('orders.kitchen-slip.print-status', ['order' => $order, 'printerJob' => '__JOB__']),
                'activeJobId' => $activePrintJobs[$order->id] ?? null,
            ]))"
        >
            <button
                type="button"
                @click="send()"
                :disabled="busy"
                :title="'{{ __('Print to Kitchen Printer') }}'"
                class="w-full h-full px-2 py-3 inline-flex items-center justify-center text-center border border-slate-200 text-gray-600 text-sm font-semibold rounded-lg hover:bg-gray-50 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <span x-show="state === 'idle'">{{ __('Direct Print') }}</span>
                <span x-show="state === 'sending'">{{ __('Sending…') }}</span>
                <span x-show="state === 'printing'">{{ __('Printing…') }}</span>
                <span x-show="state === 'printed'" class="text-green-700">{{ __('Printed!') }}</span>
                <span x-show="state === 'failed'" class="text-red-700">{{ __('Failed') }}</span>
                <span x-show="state === 'waiting'" class="text-amber-700">{{ __('Still printing…') }}</span>
            </button>
        </div>

        <x-confirm-form
            :action="route('orders.update-status', $order)"
            method="PATCH"
            class="col-span-2"
            :title="__('Cancel this order?')"
            :message="__('This cannot be undone.')"
            :confirm-label="__('Cancel Order')"
        >
            <input type="hidden" name="status" value="cancelled">
            <button type="submit" class="w-full px-4 py-3 border border-slate-200 text-gray-600 text-sm font-semibold rounded-lg hover:bg-gray-50 transition">
                {{ __('Cancel') }}
            </button>
        </x-confirm-form>
    </div>
</div>
