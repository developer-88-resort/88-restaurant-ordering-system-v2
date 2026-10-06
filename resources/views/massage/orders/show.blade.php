{{-- A massage order, laid out like the restaurant's order page
     (orders/show): the services on the left, and Location, Order Status,
     Payment and the payment form (the same shared payment rows) on the right. --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="flex flex-wrap items-center gap-3 text-2xl font-semibold leading-tight tracking-tight text-slate-900">
                <span class="tabular-nums">{{ $order->order_number }}</span>
                <span class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-500">{{ __('Massage') }}</span>
            </h2>
            <a href="{{ route('massage.orders.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-slate-400">
                {{ __('Back to Massage Orders') }}
            </a>
        </div>
    </x-slot>

    @php
        $canVoid = in_array(auth()->user()->role, [\App\Enums\UserRole::Superadmin, \App\Enums\UserRole::Admin], true);
        $recorded = $order->payments->where('status', \App\Enums\OrderPaymentStatus::Recorded);
    @endphp

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_400px] 2xl:grid-cols-[minmax(0,1fr)_440px]">
        <div class="min-w-0 space-y-5">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 tabular-nums">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Service') }}</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Qty') }}</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Unit Price') }}</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Subtotal') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($order->items as $item)
                                {{-- The line's subtotal includes its add-ons, listed under it. --}}
                                <tr>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                        {{ $item->label() }}
                                        @foreach ($item->addOns as $addOn)
                                            <span class="mt-1 block text-xs font-normal text-slate-500">+ {{ $addOn->name }} &times; {{ $addOn->quantity }} &middot; ₱{{ number_format((float) $addOn->subtotal, 2) }}</span>
                                        @endforeach
                                    </td>
                                    <td class="px-6 py-4 text-right align-top text-sm text-gray-700">{{ $item->quantity }}</td>
                                    <td class="px-6 py-4 text-right align-top text-sm text-gray-600">₱{{ number_format((float) $item->unit_price, 2) }}</td>
                                    <td class="px-6 py-4 text-right align-top text-sm font-semibold text-gray-900">₱{{ number_format((float) $item->subtotal, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-50">
                            <tr>
                                <td colspan="3" class="px-6 py-3 text-right text-sm font-semibold text-gray-900">{{ __('Total') }}</td>
                                <td class="px-6 py-3 text-right text-lg font-semibold tabular-nums text-slate-900">₱{{ number_format((float) $order->total_amount, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            @if ($order->notes)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('Massage notes') }}</p>
                    <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $order->notes }}</p>
                </div>
            @endif

            @if ($order->payments->isNotEmpty())
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-6 py-4">
                        <h3 class="text-sm font-bold text-slate-900">{{ __('Payment entries') }}</h3>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($order->payments as $payment)
                            @php $voided = $payment->status === \App\Enums\OrderPaymentStatus::Voided; @endphp
                            <li class="flex items-start justify-between gap-4 px-6 py-3.5 text-sm {{ $voided ? 'opacity-60' : '' }}">
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900 {{ $voided ? 'line-through' : '' }}">{{ $payment->displayLabel() }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        {{ $payment->received_at->format('M d, Y g:i A') }} · {{ $payment->receivedBy?->name ?? '—' }}
                                        @if ($payment->charged_to) · {{ __('Room/Guest') }}: {{ $payment->charged_to }} @endif
                                        @if ($payment->reference) · {{ __('Ref') }}: {{ $payment->reference }} @endif
                                        @if ($payment->approval_code) · {{ __('Approval') }}: {{ $payment->approval_code }} @endif
                                    </p>
                                    @if ($voided)
                                        <p class="mt-0.5 text-xs font-semibold text-red-600">{{ __('Voided') }} {{ $payment->voided_at?->format('M d, g:i A') }}</p>
                                    @endif
                                </div>
                                <span class="shrink-0 font-semibold tabular-nums text-slate-900">₱{{ number_format((float) $payment->amount, 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- Details / actions --}}
        <div class="w-full min-w-0 space-y-5">
            <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('Room / Guest') }}</p>
                    <p class="text-sm font-medium text-gray-900">{{ $order->whoLabel() }}</p>
                    <p class="mt-0.5 text-xs text-gray-400">{{ __('Created') }} {{ $order->created_at->format('M d, Y g:i A') }} · {{ $order->creator?->name ?? '—' }}</p>
                </div>

                <div>
                    <p class="mb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('Massage Status') }}</p>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $order->status->badgeClasses() }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $order->status->dotClasses() }}"></span>
                        {{ $order->status->label() }}
                    </span>
                    @if ($order->status === \App\Enums\MassageOrderStatus::Cancelled)
                        <p class="mt-1 text-xs text-gray-400">{{ $order->cancelled_at?->format('M d, Y g:i A') }}</p>
                    @endif
                </div>

                <div>
                    <p class="mb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('Payment') }}</p>
                    @if ($order->status === \App\Enums\MassageOrderStatus::Paid)
                        <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800">{{ __('Paid') }}</span>
                        <span class="ml-1 text-xs uppercase text-gray-400">{{ $recorded->map(fn ($p) => $p->payment_method->label())->unique()->implode(' + ') }}</span>
                        <p class="mt-1 text-xs text-gray-400">{{ __('Paid on') }} {{ $order->paid_at?->format('M d, Y g:i A') }}</p>
                        <dl class="mt-3 space-y-1 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-500">{{ __('Total Due') }}</dt><dd class="text-gray-900">₱{{ number_format((float) $order->total_amount, 2) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">{{ __('Amount Received') }}</dt><dd class="text-gray-900">₱{{ number_format((float) $recorded->sum(fn ($p) => (float) ($p->tendered_amount ?? $p->amount)), 2) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">{{ __('Change Due') }}</dt><dd class="text-gray-900">₱{{ number_format((float) $recorded->sum(fn ($p) => (float) $p->change_amount), 2) }}</dd></div>
                        </dl>
                    @else
                        <span class="inline-flex rounded-full {{ $order->isOpen() ? 'bg-red-100 text-red-800' : 'bg-gray-200 text-gray-700' }} px-2.5 py-1 text-xs font-semibold">{{ __('Unpaid') }}</span>
                    @endif
                </div>

                @if ($order->isOpen())
                    <div class="border-t border-slate-100 pt-4">
                        @if ($errors->any())
                            <div class="mb-3 space-y-1 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-700">
                                @foreach ($errors->all() as $error)
                                    <p>{{ $error }}</p>
                                @endforeach
                            </div>
                        @endif

                        <form method="POST" action="{{ route('massage.orders.pay', $order) }}" x-data="orderPayment(@js($paymentConfig))" @submit.prevent="open = true">
                            @csrf
                            <div class="space-y-4">
                                <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-4 text-sm">
                                    <div class="flex justify-between gap-3 tabular-nums font-semibold text-gray-900">
                                        <span>{{ __('Estimated Total Due') }}</span>
                                        <span class="text-[#8A3330]">₱{{ number_format((float) $order->total_amount, 2) }}</span>
                                    </div>
                                </div>

                                @include('orders.partials.payment-rows')

                                <button type="submit" class="min-h-12 w-full rounded-xl bg-slate-800 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-900 focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
                                    {{ __('Finalize Payment') }}
                                </button>
                            </div>

                            <dialog x-ref="dialog" x-show="open" x-cloak
                                    x-effect="open ? $refs.dialog.showModal() : $refs.dialog.close()"
                                    @cancel="open = false" @click="$event.target === $refs.dialog && (open = false)"
                                    class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-xl border border-slate-200 p-0 backdrop:bg-black/40">
                                <div class="p-6">
                                    <h3 class="font-semibold text-gray-900">{{ __('Confirm Payment') }}</h3>
                                    <dl class="mt-4 space-y-2 text-sm">
                                        <div class="flex justify-between gap-3 tabular-nums">
                                            <dt class="text-gray-500">{{ __('Estimated Total Due') }}</dt>
                                            <dd class="font-medium text-gray-900" x-text="'₱' + estimatedTotalDue.toFixed(2)"></dd>
                                        </div>
                                        <div class="flex justify-between gap-3 tabular-nums">
                                            <dt class="text-gray-500">{{ __('Total Paid') }}</dt>
                                            <dd class="font-medium text-gray-900" x-text="'₱' + totalPaid.toFixed(2)"></dd>
                                        </div>
                                        <div class="flex justify-between gap-3 tabular-nums" x-show="totalChange > 0">
                                            <dt class="text-gray-500">{{ __('Cash Change') }}</dt>
                                            <dd class="font-medium text-gray-900" x-text="'₱' + totalChange.toFixed(2)"></dd>
                                        </div>
                                    </dl>
                                    <p class="mt-2 text-xs text-red-600" x-show="insufficientAmount" x-cloak
                                       x-text="'{{ __('Insufficient payment') }} — ₱' + remainingBalance.toFixed(2) + ' {{ __('still due.') }}'"></p>
                                    <div class="mt-6 flex justify-end gap-3">
                                        <button type="button" @click="open = false" class="text-sm font-medium text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</button>
                                        <button type="button" @click="open = false; $root.submit()" :disabled="insufficientAmount"
                                                class="rounded-md bg-[#8A3330] px-4 py-2 text-sm font-medium text-white hover:bg-[#742927] disabled:cursor-not-allowed disabled:opacity-50">
                                            {{ __('Confirm Payment') }}
                                        </button>
                                    </div>
                                </div>
                            </dialog>
                        </form>
                    </div>
                @endif

                <div class="space-y-3 border-t border-slate-100 pt-4">
                    @if ($order->isOpen())
                        <x-confirm-form
                            :action="route('massage.orders.cancel', $order)"
                            method="POST"
                            :title="__('Cancel this massage order?')"
                            :message="__('Use this only if the guest is not getting the massage. It stays on record as cancelled.')"
                            :confirm-label="__('Cancel Massage Order')"
                        >
                            <button type="submit" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                                {{ __('Cancel Massage Order') }}
                            </button>
                        </x-confirm-form>
                    @elseif ($order->status === \App\Enums\MassageOrderStatus::Paid && $canVoid)
                        <x-confirm-form
                            :action="route('massage.orders.void-payment', $order)"
                            method="POST"
                            :title="__('Void this payment?')"
                            :message="__('The payment stays on record as voided and drops out of Reports. The order becomes unpaid so it can be paid again correctly.')"
                            :confirm-label="__('Void Payment')"
                        >
                            <button type="submit" class="w-full rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">
                                {{ __('Void Payment') }}
                            </button>
                        </x-confirm-form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
