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
@endphp

<div class="rounded-xl bg-white border border-[#E5DDD0] shadow-sm">
    <div class="px-5 pt-4 pb-3">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <span class="text-lg font-bold text-gray-900">{{ $order->orderNumber() }}</span>
                <p class="text-sm text-gray-500 truncate">
                    @if ($order->order_type === \App\Enums\OrderType::Takeout)
                        {{ __('Take-out') }}
                    @else
                        {{ $order->locationLabel() }}
                        <span class="text-gray-300">&bull;</span>
                        {{ $order->order_type->label() }}
                    @endif
                </p>
                @if ($order->guestSession)
                    <p class="text-xs font-semibold text-teal-700 truncate">{{ $order->guestSession->displayLabel() }}</p>
                @endif
                @if ($order->customer_name)
                    <p class="text-xs text-[#8A3330] font-medium truncate">{{ __('Ordered by') }}: {{ $order->customer_name }}</p>
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
                class="text-sm font-semibold text-gray-400 shrink-0"
                x-text="(() => {
                    const diff = Math.max(0, Math.floor((now - start) / 1000));
                    const m = Math.floor(diff / 60);
                    const s = diff % 60;
                    return m + ':' + String(s).padStart(2, '0');
                })()"
            ></div>
        </div>

        @if ($order->sourceQuotation)
            <span class="mt-2 inline-flex items-center gap-1 rounded-full bg-amber-100 border border-amber-300 px-2.5 py-1 text-xs font-bold uppercase text-amber-800">
                {{ __('Advance Order') }} · {{ $order->sourceQuotation->quotation_number }}
            </span>
        @endif
    </div>

    <div class="border-t border-[#E5DDD0]"></div>

    <div class="px-5 py-4 space-y-3">
        @foreach ($itemsByBatch as $batchNumber => $batchItems)
            <div>
                @if ($hasMultipleBatches)
                    <p class="mb-1.5 text-[10px] font-bold uppercase tracking-wide text-teal-700">
                        {{ $batchNumber ? __('Batch') . ' #' . $batchNumber : __('Earlier round') }}
                    </p>
                @endif
                <div class="space-y-1.5">
                    @foreach ($batchItems as $item)
                        @php $itemCancelled = $item->isFullyCancelled(); @endphp
                        <div class="text-sm {{ $itemCancelled ? 'text-gray-400' : 'text-gray-800' }}">
                            {{-- A weighed line is one piece of food off the scale, so the
                                 kitchen needs the grams, not a "1×". --}}
                            @if ($item->isWeighed())
                                <span class="font-semibold {{ $itemCancelled ? 'line-through' : '' }}">{{ number_format((float) $item->netWeightGrams()) }}g</span>
                            @else
                                <span class="font-semibold {{ $itemCancelled ? 'line-through' : '' }}">{{ $item->quantity }}&times;</span>
                            @endif
                            <span class="{{ $itemCancelled ? 'line-through' : '' }}">{{ $item->item_name }}</span>
                            @if ($item->isWeighed() && $item->cookingLabel())
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded bg-teal-50 text-teal-700 text-[10px] font-bold uppercase">{{ $item->cookingLabel() }}</span>
                            @endif
                            @if ($itemCancelled)
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded bg-red-100 text-red-700 text-[10px] font-bold uppercase">{{ $item->isWeighed() ? __('Voided') : __('Cancelled') }}</span>
                            @elseif ($item->cancelledQuantity() > 0)
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded bg-red-100 text-red-700 text-[10px] font-bold uppercase">−{{ $item->cancelledQuantity() }} {{ __('cancelled') }}</span>
                            @endif
                            @if ($item->cooking_note)
                                <div class="pl-5 text-xs text-teal-700">{{ $item->cooking_note }}</div>
                            @endif
                            @if ($item->notes)
                                <div class="pl-5 text-xs text-gray-400">{{ $item->notes }}</div>
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
    </div>

    <div class="px-5 pb-5 flex gap-2">
        <form action="{{ route('orders.update-status', $order) }}" method="POST" class="flex-1">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="{{ $nextStatus }}">
            <button type="submit" class="w-full {{ $accentClasses['button'] }} text-white text-xs font-bold uppercase tracking-wider rounded-lg py-3 transition">
                {{ $buttonLabel }}
            </button>
        </form>

        <x-confirm-form
            :action="route('orders.update-status', $order)"
            method="PATCH"
            class="shrink-0"
            :title="__('Cancel this order?')"
            :message="__('This cannot be undone.')"
            :confirm-label="__('Cancel Order')"
        >
            <input type="hidden" name="status" value="cancelled">
            <button type="submit" class="h-full px-4 border border-[#E5DDD0] text-gray-600 text-xs font-bold uppercase tracking-wider rounded-lg hover:bg-gray-50 transition">
                {{ __('Cancel') }}
            </button>
        </x-confirm-form>
    </div>
</div>
