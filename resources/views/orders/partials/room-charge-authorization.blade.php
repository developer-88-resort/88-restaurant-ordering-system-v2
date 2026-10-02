{{--
    Room Charge authorization — one block per room charge on this invoice,
    only on the customer receipt (view / print / PDF), never on the kitchen
    slip. The guest prints their name and signs; front desk posts the charge
    to the room in WinCloud and writes the ORDER NO. (printed big for that
    reason) in its Narration field.

    With Settings → room_charge_front_desk_copy on (the default), each block
    prints twice with a cut line: the outlet keeps the signed copy, front desk
    gets the other one to post.

    Inline styles only: the same markup renders in the web receipt, the
    thermal print page and dompdf.

    Expects: $order, $invoice.
--}}
@php
    $roomCharges = $order->payments
        ->where('order_invoice_snapshot_id', $invoice->id)
        ->where('status', \App\Enums\OrderPaymentStatus::Recorded)
        ->where('payment_method', \App\Enums\PaymentMethod::RoomCharge)
        ->values();
    $copies = \App\Models\Setting::current()->room_charge_front_desk_copy
        ? [__('OUTLET COPY'), __('FRONT DESK COPY')]
        : [null];
@endphp

@if ($roomCharges->isNotEmpty())
    <div class="room-charge-authorizations" style="margin-top: 14px;">
        @foreach ($roomCharges as $charge)
            @foreach ($copies as $copyIndex => $copyLabel)
                @if ($copyIndex > 0)
                    <div style="margin: 12px 0; border-top: 1px dashed #000; text-align: center; font-size: 10px; line-height: 0;">
                        <span style="background: #fff; padding: 0 6px;">&#9986; {{ __('cut here') }}</span>
                    </div>
                @endif
                <div style="border: 1px solid #000; padding: 8px 10px; font-size: 11px; line-height: 1.45; page-break-inside: avoid; color: #000;">
                    <div style="text-align: center; font-weight: bold; font-size: 12px; letter-spacing: 0.04em;">{{ __('ROOM CHARGE AUTHORIZATION') }}</div>
                    @if ($copyLabel)
                        <div style="text-align: center; font-size: 9px; letter-spacing: 0.08em;">{{ $copyLabel }}</div>
                    @endif

                    <div style="margin-top: 6px;">
                        {{ __('Charge to') }}:
                        <strong style="font-size: 13px;">
                            @if ($charge->roomLabel() !== null)
                                {{ __('ROOM') }} {{ $charge->roomLabel() }}@if ($charge->roomTypeName()) ({{ $charge->roomTypeName() }})@endif
                            @else
                                {{ $charge->charged_to ?: '—' }}
                            @endif
                        </strong>
                    </div>
                    <div>{{ __('Amount') }}: <strong>&#8369;{{ number_format($charge->amount, 2) }}</strong></div>

                    <div style="margin-top: 4px;">
                        {{ __('Order no.') }}
                        <div style="font-size: 20px; font-weight: bold; letter-spacing: 0.03em;">{{ $order->orderNumber() }}</div>
                    </div>
                    <div>{{ __('Receipt no.') }}: {{ $invoice->invoice_number }}</div>

                    @if ($charge->guest_name || $charge->guest_ref)
                        <div style="margin-top: 4px;">
                            {{ __('Guest on record') }}: {{ $charge->guest_name ?: '—' }}@if ($charge->guest_ref) · {{ __('Ref') }}: {{ $charge->guest_ref }}@endif
                        </div>
                    @endif

                    <div style="margin-top: 12px;">{{ __('Guest name (print)') }}: ________________________</div>
                    <div style="margin-top: 12px;">{{ __('Guest signature') }}: &nbsp;&nbsp;________________________</div>

                    <div style="margin-top: 8px; font-size: 10px;">
                        {{ __('Cashier') }}: {{ $charge->receivedBy?->name ?? '—' }}
                        &nbsp;·&nbsp; {{ __('Date/time') }}: {{ $charge->received_at?->format('M d, Y g:i A') }}
                    </div>
                    <div style="font-size: 9px;">{{ __('To be settled at front desk.') }}</div>
                </div>
            @endforeach
        @endforeach
    </div>
@endif
