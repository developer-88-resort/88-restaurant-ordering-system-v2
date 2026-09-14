{{--
    Narrow-width-safe kitchen slip content — plain flex rows in a fixed-width
    monospace layout, mirroring orders/partials/receipt-print-body.blade.php's
    approach, but this is a prep ticket, not a billing document: no prices,
    amounts, subtotals, tax, or payment totals anywhere below. "Ordered By"
    is always $order->creator (who took the order), never the staffer who
    happens to click Print.
--}}
<div class="center">
    <p class="name">{{ __('Kitchen Order Slip') }}</p>
    @if ($order->sourceQuotation)
        <p class="muted small">{{ __('Advance Order') }} &middot; {{ $order->sourceQuotation->quotation_number }}</p>
    @endif
</div>

<div class="section">
    <div class="row"><span class="label">{{ __('Order No.') }}</span><span class="value">{{ $order->orderNumber() }}</span></div>
    <div class="row"><span class="label">{{ __('Date') }}</span><span class="value">{{ $order->created_at->format('M d, Y g:i A') }}</span></div>
    <div class="row"><span class="label">{{ __('Location') }}</span><span class="value">{{ $order->locationLabel() }}</span></div>
    @if ($order->guestSession)
        <div class="row"><span class="label">{{ __('Guest') }}</span><span class="value">{{ $order->guestSession->displayLabel() }}</span></div>
    @endif
    @if ($order->customer_name)
        <div class="row"><span class="label">{{ __('Customer') }}</span><span class="value">{{ $order->customer_name }}</span></div>
    @endif
    <div class="row"><span class="label">{{ __('Order Type') }}</span><span class="value">{{ $order->order_type->label() }}</span></div>
    <div class="row"><span class="label">{{ __('Ordered By') }}</span><span class="value">{{ $order->creator->name ?? __('Unknown') }}</span></div>
</div>

@php
    $itemsByBatch = $order->items->groupBy('batch_number');
    $hasMultipleBatches = $itemsByBatch->count() > 1;
@endphp

@foreach ($itemsByBatch as $batchNumber => $batchItems)
    <div class="section">
        @if ($hasMultipleBatches)
            <p class="muted small" style="font-weight: bold; text-transform: uppercase; margin-bottom: 4px;">
                {{ $batchNumber ? __('Batch').' #'.$batchNumber : __('Earlier round') }}
            </p>
        @endif

        @foreach ($batchItems as $item)
            @php $itemCancelled = $item->isFullyCancelled(); @endphp
            <div class="row" style="{{ $itemCancelled ? 'text-decoration: line-through;' : '' }}">
                <span class="label item-name">
                    @if ($item->isWeighed())
                        {{ number_format((float) $item->netWeightGrams()) }}g
                    @else
                        {{ $item->quantity }}&times;
                    @endif
                    {{ $item->item_name }}
                </span>
            </div>
            @if ($item->isWeighed() && $item->cookingLabel())
                <div class="item-sub"><span>{{ $item->cookingLabel() }}</span></div>
            @endif
            @if ($itemCancelled)
                <div class="item-cancelled"><span>{{ $item->isWeighed() ? __('VOIDED') : __('CANCELLED') }}</span></div>
            @elseif ($item->cancelledQuantity() > 0)
                <div class="item-sub"><span>&minus;{{ $item->cancelledQuantity() }} {{ __('cancelled') }}</span></div>
            @endif
            @if ($item->cooking_note)
                <div class="item-sub"><span>{{ $item->cooking_note }}</span></div>
            @endif
            @if ($item->notes)
                <div class="item-sub"><span>{{ __('Note') }}: {{ $item->notes }}</span></div>
            @endif
        @endforeach
    </div>
@endforeach

@if ($order->notes)
    <div class="section">
        <p class="muted small" style="font-weight: bold;">{{ __('Order Notes') }}</p>
        <p class="small">{{ $order->notes }}</p>
    </div>
@endif

<p class="footer">{{ __('Kitchen copy — not valid as receipt.') }}</p>
