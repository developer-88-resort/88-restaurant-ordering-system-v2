{{--
    Narrow-width-safe kitchen slip content — plain flex rows in a fixed-width
    monospace layout, mirroring orders/partials/receipt-print-body.blade.php's
    approach. It shows each line's price, the discounts picked for the slip
    on the Kitchen Display and the total — but it is still not the receipt:
    no VAT anywhere, and a discount is a plain share of the price (see
    App\Services\Printing\OrderSlipTotals). The receipt keeps its own figures.

    The last row of the header block distinguishes who to credit for the
    order, via Order::isStaffCreated():
      - Staff-taken (New Order, weigh station, converted quotation): label
        "Waiter", value the ORIGINAL creator ($order->creator) — never
        whoever happens to be logged in when the slip is (re)printed. A
        walk-in's typed name (WeighStationController::walkInOrder()) still
        gets its own separate "Customer" row here — that's genuinely
        different information from who the staffer is.
      - Customer QR self-order: label "Ordered By", value the customer's
        own name (falling back to "Customer" when none was given) — never
        a staff name, even if a staff session happened to be active in the
        browser that submitted the order. The Guest/Customer rows below are
        SKIPPED for this case: for a QR order they'd just repeat the exact
        same name ("Guest: Andrei" / "Customer: Andrei" / "Ordered By:
        Andrei" all at once) since guest_session's display label and
        customer_name resolve to the same thing here — "Ordered By" alone
        already says it.
--}}
<div class="center">
    <p class="name">{{ __('Kitchen Order Slip') }}</p>
    {{-- Only a standalone advance order; one added to this slip is labelled on its own lines. --}}
    @if ($openingQuotation = $order->openingQuotation())
        <p class="muted small">{{ __('Advance Order') }} &middot; {{ $openingQuotation->quotation_number }}</p>
    @endif
</div>

<div class="section">
    <div class="row"><span class="label">{{ __('Order No.') }}</span><span class="value">{{ $order->orderNumber() }}</span></div>
    <div class="row"><span class="label">{{ __('Date') }}</span><span class="value">{{ $order->created_at->format('M d, Y g:i A') }}</span></div>
    <div class="row"><span class="label">{{ __('Location') }}</span><span class="value">{{ $order->locationLabel() }}</span></div>
    {{-- A table can have several slips open at once — the number tells the
         kitchen which ticket this is. --}}
    @if ($order->slip_number)
        <div class="row"><span class="label">{{ __('Slip') }}</span><span class="value">#{{ $order->slip_number }}</span></div>
    @endif
    @if ($order->isStaffCreated())
        @if ($order->guestSession)
            <div class="row"><span class="label">{{ __('Guest') }}</span><span class="value">{{ $order->guestSession->displayLabel() }}</span></div>
        @endif
        @if ($order->customer_name)
            <div class="row"><span class="label">{{ __('Customer') }}</span><span class="value">{{ $order->customer_name }}</span></div>
        @endif
    @endif
    <div class="row"><span class="label">{{ __('Order Type') }}</span><span class="value">{{ $order->order_type->label() }}</span></div>
    {{-- Optional and dine-in only, so absent on plenty of slips — the row is
         dropped entirely rather than printing an empty or zero count. --}}
    @if ($order->pax)
        <div class="row"><span class="label">{{ __('Pax') }}</span><span class="value">{{ $order->pax }}</span></div>
    @endif
    @if ($order->isStaffCreated())
        <div class="row"><span class="label">{{ __('Waiter') }}</span><span class="value">{{ $order->creator->name ?? __('Unknown') }}</span></div>
    @else
        <div class="row"><span class="label">{{ __('Ordered By') }}</span><span class="value">{{ $order->customer_name ?: $order->guestSession?->displayLabel() ?: __('Customer') }}</span></div>
    @endif
</div>

@php
    $itemsByBatch = $order->items->groupBy('batch_number');
    $hasMultipleBatches = $itemsByBatch->count() > 1;
    $slipTotals = \App\Services\Printing\OrderSlipTotals::for($order);
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
                {{-- Net of any cancellation, so the lines add up to the subtotal. --}}
                @unless ($itemCancelled)
                    <span class="value amount">{{ number_format((float) $item->lineTotalNet(), 2) }}</span>
                @endunless
            </div>
            @if (! $itemCancelled && ! $item->isWeighed() && $item->activeQuantity() > 1)
                <div class="item-sub"><span>@ {{ number_format((float) $item->unit_price, 2) }}</span></div>
            @endif
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

<div class="section">
    <div class="row"><span class="label">{{ __('Subtotal') }}</span><span class="value amount">{{ number_format((float) $slipTotals['subtotal'], 2) }}</span></div>
    @foreach ($slipTotals['discounts'] as $discount)
        <div class="row">
            <span class="label">{{ $discount['name'] }}@if ($discount['rate']) ({{ $discount['rate'] }})@endif</span>
            <span class="value amount">-{{ number_format((float) $discount['amount'], 2) }}</span>
        </div>
        @if ($discount['qualified_name'] || $discount['item_names'] || bccomp($discount['basis'], $slipTotals['subtotal'], 2) !== 0)
            <div class="item-sub">
                <span>
                    @if ($discount['qualified_name']){{ $discount['qualified_name'] }} &middot; @endif
                    @if ($discount['item_names'])
                        {{ implode(', ', $discount['item_names']) }}
                    @else
                        {{ __('on') }} {{ number_format((float) $discount['basis'], 2) }}
                    @endif
                </span>
            </div>
        @endif
    @endforeach
    <div class="row total-row"><span class="label">{{ __('TOTAL') }}</span><span class="value amount">{{ number_format((float) $slipTotals['total'], 2) }}</span></div>
</div>

@if ($order->notes)
    <div class="section">
        <p class="muted small" style="font-weight: bold;">{{ __('Order Notes') }}</p>
        <p class="small">{{ $order->notes }}</p>
    </div>
@endif

<p class="footer">{{ __('Kitchen copy — not valid as receipt.') }}</p>
