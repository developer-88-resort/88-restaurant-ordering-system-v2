<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight font-mono">
                {{ $order->orderNumber() }}
            </h2>
            <a href="{{ route('orders.index') }}" class="text-sm text-[#8A3330] hover:underline font-medium">
                {{ __('Back to Orders') }}
            </a>
        </div>
    </x-slot>

    <div class="flex flex-col lg:flex-row gap-6 items-start">
        {{-- Items --}}
        <div class="flex-1 w-full"
             x-data="{
                cancelOpen: false,
                cancelItem: null,
                cancelQty: 1,
                cancelReason: '',
                cancelNotes: '',
                restoreInventory: false,
                managerEmail: '',
                managerPassword: '',
                needsApproval: {{ Js::from($order->status !== \App\Enums\OrderStatus::Pending || $order->payment_status === \App\Enums\PaymentStatus::Paid) }},
                isStaff: {{ Js::from(auth()->user()->role === \App\Enums\UserRole::Staff) }},
                openCancel(item) {
                    this.cancelItem = item;
                    this.cancelQty = 1;
                    this.cancelReason = '';
                    this.cancelNotes = '';
                    this.restoreInventory = false;
                    this.cancelOpen = true;
                },
                get cancelUrl() {
                    return this.cancelItem ? '{{ url('orders/'.$order->id.'/items') }}/' + this.cancelItem.id + '/cancel' : '#';
                },
                get previewAmount() {
                    if (! this.cancelItem) return 0;
                    return this.cancelItem.unitPrice * this.cancelQty;
                },
                weightOpen: false,
                weightItem: null,
                weightGrams: 0,
                tareGrams: 0,
                weightPieces: 1,
                weightReason: '',
                openWeight(item) {
                    this.weightItem = item;
                    this.weightGrams = item.weightGrams;
                    this.tareGrams = item.tareGrams;
                    this.weightPieces = item.pieces;
                    this.weightReason = '';
                    this.weightOpen = true;
                },
                get weightUrl() {
                    return this.weightItem ? '{{ url('orders/'.$order->id.'/items') }}/' + this.weightItem.id + '/weight' : '#';
                },
                get netGrams() {
                    if (! this.weightItem) return 0;
                    return Math.max(0, (Number(this.weightGrams) || 0) - (Number(this.tareGrams) || 0));
                },
                get tareTooHeavy() {
                    if (! this.weightItem) return false;
                    return (Number(this.tareGrams) || 0) >= (Number(this.weightGrams) || 0);
                },
                {{-- Mirrors WeighedLinePricer for the on-screen preview only.
                     The server always recomputes the charge it actually bills. --}}
                get weightPreview() {
                    if (! this.weightItem) return 0;
                    const base = Math.round((this.netGrams / 1000) * this.weightItem.pricePerKilo * 100) / 100;
                    const pieces = Math.max(1, Number(this.weightPieces) || 1);
                    return base + this.weightItem.surcharge * pieces;
                },
             }">
            <div class="bg-white border border-[#E5DDD0] rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[#E5DDD0]">
                        <thead class="bg-[#FAF6EE]">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Item') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Qty') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Unit Price') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Subtotal') }}</th>
                                @if ($order->status !== \App\Enums\OrderStatus::Cancelled)
                                    <th class="px-6 py-3"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E5DDD0]">
                            @foreach ($order->items as $item)
                                @php
                                    $fullyCancelled = $item->isFullyCancelled();
                                    $activeQty = $item->activeQuantity();
                                @endphp
                                <tr>
                                    <td class="px-6 py-4 text-sm font-medium {{ $fullyCancelled ? 'text-gray-400 line-through' : 'text-gray-900' }}">
                                        {{ $item->item_name }}
                                        @if ($item->isWeighed())
                                            <span class="ml-1 inline-flex px-1.5 py-0.5 rounded bg-teal-50 text-teal-700 text-[10px] font-bold uppercase align-middle">{{ __('Weighed') }}</span>
                                            <span class="block text-xs font-normal text-[#8A7B6D] no-underline mt-0.5">{{ $item->weightLabel() }}</span>
                                            @if ($item->cookingLabel())
                                                <span class="block text-xs font-normal text-[#8A7B6D]">{{ __('Cooking') }}: {{ $item->cookingLabel() }}</span>
                                            @endif
                                            @if ($item->cooking_note)
                                                <span class="block text-xs font-normal text-gray-500">{{ $item->cooking_note }}</span>
                                            @endif
                                            @if ($item->price_override_reason)
                                                <span class="block text-xs font-normal text-amber-700 mt-0.5">
                                                    {{ __('Weight corrected') }}: {{ $item->price_override_reason }}
                                                </span>
                                            @endif
                                        @endif
                                        @if ($item->notes)
                                            <p class="text-xs text-gray-500 font-normal mt-0.5">{{ $item->notes }}</p>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-600">
                                        {{-- A weighed line has no countable quantity: the "how much"
                                             is the grams, so no stepper and no ×N is shown. --}}
                                        @if ($item->isWeighed())
                                            <span class="text-xs text-[#8A7B6D]">{{ number_format((float) $item->netWeightGrams()) }} g</span>
                                        @else
                                            {{ $item->quantity }}
                                        @endif
                                        @if (! $fullyCancelled && $item->cancelledQuantity() > 0)
                                            <span class="block text-xs text-red-600">
                                                {{ $item->isWeighed() ? __('voided') : '−'.$item->cancelledQuantity().' '.__('cancelled') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-600">
                                        @if ($item->isWeighed())
                                            <span class="text-xs text-[#8A7B6D]">₱{{ number_format((float) $item->price_per_kilo_snapshot, 2) }}/kg</span>
                                        @else
                                            ₱{{ number_format($item->unit_price, 2) }}
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-medium {{ $fullyCancelled ? 'text-gray-400 line-through' : 'text-gray-900' }}">₱{{ number_format($item->subtotal, 2) }}</td>
                                    @if ($order->status !== \App\Enums\OrderStatus::Cancelled)
                                        <td class="px-6 py-4 text-right">
                                            @if ($activeQty > 0)
                                                @if ($item->isWeighed())
                                                    <div class="flex flex-col items-end gap-1">
                                                        <button type="button"
                                                                @click="openWeight({{ Js::from([
                                                                    'id' => $item->id,
                                                                    'name' => $item->item_name,
                                                                    'weightGrams' => (int) $item->weight_grams,
                                                                    'tareGrams' => (int) $item->tare_grams,
                                                                    'pieces' => $item->pieces ? (int) $item->pieces : 1,
                                                                    'pricePerKilo' => (float) $item->price_per_kilo_snapshot,
                                                                    'surcharge' => (float) ($item->cookingStyle->surcharge ?? 0),
                                                                ]) }})"
                                                                class="text-xs font-medium text-[#8A3330] hover:underline">
                                                            {{ __('Edit weight') }}
                                                        </button>
                                                        <button type="button"
                                                                @click="openCancel({{ Js::from([
                                                                    'id' => $item->id,
                                                                    'name' => $item->item_name,
                                                                    'activeQty' => $activeQty,
                                                                    'unitPrice' => (float) $item->unit_price,
                                                                    'isWeighed' => true,
                                                                ]) }})"
                                                                class="text-xs font-medium text-red-600 hover:underline">
                                                            {{ __('Void line') }}
                                                        </button>
                                                    </div>
                                                @else
                                                    <button type="button"
                                                            @click="openCancel({{ Js::from([
                                                                'id' => $item->id,
                                                                'name' => $item->item_name,
                                                                'activeQty' => $activeQty,
                                                                'unitPrice' => (float) $item->unit_price,
                                                                'isWeighed' => false,
                                                            ]) }})"
                                                            class="text-xs font-medium text-red-600 hover:underline">
                                                        {{ __('Cancel Item') }}
                                                    </button>
                                                @endif
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                                @foreach ($item->adjustments as $adjustment)
                                    <tr class="bg-red-50/50">
                                        <td class="px-6 py-2 text-xs font-semibold text-red-700" colspan="3">
                                            {{ __('CANCELLED') }} — {{ $adjustment->reason_code->label() }} ({{ $adjustment->quantity }}×)
                                            @if ($adjustment->notes)
                                                <span class="block font-normal text-red-500 mt-0.5">{{ $adjustment->notes }}</span>
                                            @endif
                                            <span class="block font-normal text-red-400 mt-0.5">
                                                {{ $adjustment->requestedBy->name ?? __('Unknown') }}
                                                @if ($adjustment->approvedBy)
                                                    · {{ __('approved by') }} {{ $adjustment->approvedBy->name }}
                                                @endif
                                                · {{ $adjustment->created_at->format('M d, g:i A') }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-2 text-right text-xs font-semibold text-red-700">−₱{{ number_format($adjustment->reversed_amount, 2) }}</td>
                                        @if ($order->status !== \App\Enums\OrderStatus::Cancelled)
                                            <td></td>
                                        @endif
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                        <tfoot class="bg-[#FAF6EE]">
                            <tr>
                                <td colspan="3" class="px-6 py-3 text-right text-sm font-semibold text-gray-900">{{ __('Total') }}</td>
                                <td class="px-6 py-3 text-right text-base font-bold text-[#8A3330]">₱{{ number_format($order->total_amount, 2) }}</td>
                                @if ($order->status !== \App\Enums\OrderStatus::Cancelled)
                                    <td></td>
                                @endif
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Cancel/void one served item --}}
            <dialog
                x-ref="cancelDialog"
                x-effect="cancelOpen ? $refs.cancelDialog.showModal() : $refs.cancelDialog.close()"
                @cancel="cancelOpen = false"
                @click="$event.target === $refs.cancelDialog && (cancelOpen = false)"
                class="rounded-xl border border-[#E5DDD0] p-0 backdrop:bg-black/40 max-w-md w-[calc(100%-2rem)] m-auto"
            >
                <form method="POST" :action="cancelUrl" class="p-6" x-show="cancelItem">
                    @csrf
                    <h3 class="font-semibold text-gray-900" x-text="cancelItem && cancelItem.isWeighed ? '{{ __('Void line') }}' : '{{ __('Cancel Item') }}'"></h3>
                    <p class="mt-1 text-sm text-gray-600" x-text="cancelItem ? cancelItem.name : ''"></p>

                    <div class="mt-4 space-y-3">
                        {{-- A weighed line is a single weighed piece of food: there is
                             no quantity to choose, so it voids whole. --}}
                        <template x-if="cancelItem && cancelItem.isWeighed">
                            <input type="hidden" name="quantity" value="1">
                        </template>
                        <template x-if="cancelItem && ! cancelItem.isWeighed">
                            <div>
                                <label class="block text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Quantity to Cancel') }}</label>
                                <input type="number" name="quantity" x-model.number="cancelQty" min="1" :max="cancelItem ? cancelItem.activeQty : 1" required
                                       class="mt-1 w-full text-sm rounded-lg border-[#E5DDD0] focus:border-red-500 focus:ring-red-500">
                            </div>
                        </template>

                        <div>
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Reason') }}</label>
                            <select name="reason_code" x-model="cancelReason" required
                                    class="mt-1 w-full text-sm rounded-lg border-[#E5DDD0] focus:border-red-500 focus:ring-red-500">
                                <option value="">{{ __('Select a reason...') }}</option>
                                @foreach (\App\Enums\OrderItemAdjustmentReason::cases() as $reason)
                                    <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Detailed Notes') }}</label>
                            <textarea name="notes" x-model="cancelNotes" rows="2" required
                                      placeholder="{{ __('e.g. Customer found a foreign object in the dish') }}"
                                      class="mt-1 w-full text-sm rounded-lg border-[#E5DDD0] focus:border-red-500 focus:ring-red-500"></textarea>
                        </div>

                        <label class="flex items-start gap-2 text-sm text-gray-600">
                            <input type="hidden" name="inventory_restored" value="0">
                            <input type="checkbox" name="inventory_restored" value="1" x-model="restoreInventory" class="mt-0.5 rounded text-red-600 focus:ring-red-500">
                            <span>
                                {{ __('Return ingredients to inventory') }}
                                <span class="block text-xs text-gray-400">{{ __('Leave unchecked for served or contaminated food.') }}</span>
                            </span>
                        </label>

                        <template x-if="needsApproval && isStaff">
                            <div class="pt-3 border-t border-dashed border-[#D9CCBA] space-y-2">
                                <p class="text-xs font-semibold text-[#8A3330]">{{ __('Manager approval required — this item is already being prepared, served, or paid.') }}</p>
                                <input type="email" name="manager_email" x-model="managerEmail" placeholder="{{ __('Manager Email') }}"
                                       class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                                <input type="password" name="manager_password" x-model="managerPassword" placeholder="{{ __('Manager Password') }}"
                                       class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                            </div>
                        </template>

                        <div class="rounded-lg bg-red-50 border border-red-100 px-4 py-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-red-700">{{ __('Charge to be removed') }}</span>
                                <span class="font-semibold text-red-700">−₱<span x-text="previewAmount.toFixed(2)"></span></span>
                            </div>
                            <p class="mt-1 text-xs text-red-500">{{ __('The receipt will keep the original line and show this reversal under it.') }}</p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" @click="cancelOpen = false" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                            {{ __('Keep Item') }}
                        </button>
                        <button type="submit" class="text-sm font-medium rounded-md px-4 py-2 bg-red-600 hover:bg-red-700 text-white">
                            {{ __('Confirm Cancellation') }}
                        </button>
                    </div>
                </form>
            </dialog>

            {{-- Correct the scale reading on a weighed line --}}
            <dialog
                x-ref="weightDialog"
                x-effect="weightOpen ? $refs.weightDialog.showModal() : $refs.weightDialog.close()"
                @cancel="weightOpen = false"
                @click="$event.target === $refs.weightDialog && (weightOpen = false)"
                class="rounded-xl border border-[#E5DDD0] p-0 backdrop:bg-black/40 max-w-md w-[calc(100%-2rem)] m-auto"
            >
                <form method="POST" :action="weightUrl" class="p-6" x-show="weightItem">
                    @csrf
                    @method('PATCH')
                    <h3 class="font-semibold text-gray-900">{{ __('Edit weight') }}</h3>
                    <p class="mt-1 text-sm text-gray-600" x-text="weightItem ? weightItem.name : ''"></p>
                    <p class="mt-1 text-xs text-[#8A7B6D]"
                       x-text="weightItem ? ('{{ __('Charged at') }} ₱' + Number(weightItem.pricePerKilo).toFixed(2) + '/kg') : ''"></p>

                    <div class="mt-4 space-y-3">
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Scale (g)') }}</label>
                                <input type="number" name="weight_grams" x-model.number="weightGrams" min="1" max="200000" required
                                       class="mt-1 w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Tare (g)') }}</label>
                                <input type="number" name="tare_grams" x-model.number="tareGrams" min="0" max="200000"
                                       class="mt-1 w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Pieces') }}</label>
                                <input type="number" name="pieces" x-model.number="weightPieces" min="1" max="999"
                                       class="mt-1 w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                            </div>
                        </div>

                        <p x-show="tareTooHeavy" x-cloak class="text-xs font-medium text-red-600">
                            {{ __('The tare weight must be less than the weight on the scale.') }}
                        </p>

                        <div>
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Reason') }}</label>
                            <input type="text" name="reason" x-model="weightReason" required maxlength="255"
                                   placeholder="{{ __('e.g. Re-weighed with the customer, first reading included the tray') }}"
                                   class="mt-1 w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                            <p class="mt-1 text-xs text-gray-400">{{ __('Recorded against this line and shown on the order screen.') }}</p>
                        </div>

                        <div class="rounded-lg bg-[#FAF6EE] border border-[#E5DDD0] px-4 py-3 flex items-center justify-between">
                            <span class="text-xs text-[#8A7B6D]" x-text="netGrams + ' g {{ __('net') }}'"></span>
                            <span class="text-base font-bold text-[#8A3330]" x-text="'₱' + weightPreview.toFixed(2)"></span>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" @click="weightOpen = false" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" :disabled="tareTooHeavy"
                                class="text-sm font-medium rounded-md px-4 py-2 bg-[#8A3330] hover:bg-[#742927] text-white disabled:opacity-40 disabled:cursor-not-allowed">
                            {{ __('Save Weight') }}
                        </button>
                    </div>
                </form>
            </dialog>

            @if ($order->notes)
                <div class="mt-6 bg-white border border-[#E5DDD0] rounded-xl p-6">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Order Notes') }}</p>
                    <p class="mt-1 text-sm text-gray-700">{{ $order->notes }}</p>
                </div>
            @endif
        </div>

        {{-- Details / actions --}}
        <div class="w-full lg:w-80 shrink-0 space-y-6">
            <div class="bg-white border border-[#E5DDD0] rounded-xl p-6 space-y-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Location') }}</p>
                    <p class="text-sm font-medium text-gray-900">{{ $order->locationLabel() }}</p>
                </div>

                @if ($order->sourceQuotation)
                    <div class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-amber-800">{{ __('ADVANCE ORDER / QUOTATION') }}</p>
                        <a href="{{ route('quotations.show', $order->sourceQuotation) }}" class="text-sm font-mono text-[#8A3330] hover:underline">
                            {{ $order->sourceQuotation->quotation_number }}
                        </a>
                        @if ($order->sourceQuotation->scheduled_for)
                            <p class="text-xs text-amber-700">{{ __('Scheduled') }}: {{ $order->sourceQuotation->scheduled_for->format('M d, Y g:i A') }}</p>
                        @endif
                    </div>
                @endif

                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E] mb-1">{{ __('Order Status') }}</p>
                    @if ($order->status->isFinal())
                        <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full {{ $order->status->badgeClasses() }}">
                            {{ $order->status->label() }}
                        </span>
                    @else
                        <form action="{{ route('orders.update-status', $order) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <select name="status" onchange="this.form.submit()"
                                    class="w-full text-xs font-semibold rounded-full px-3 py-1.5 border-0 focus:ring-2 focus:ring-[#8A3330] {{ $order->status->badgeClasses() }}">
                                @foreach (\App\Enums\OrderStatus::cases() as $status)
                                    <option value="{{ $status->value }}" @selected($order->status === $status)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                </div>

                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E] mb-1">{{ __('Payment') }}</p>
                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full {{ $order->payment_status->badgeClasses() }}">
                        {{ $order->payment_status->label() }}
                    </span>
                    @if ($order->payment_method)
                        <span class="ml-1 text-xs text-gray-400 uppercase">{{ $order->payment_method->label() }}</span>
                    @endif
                    @if ($order->payment_reference)
                        <span class="ml-1 text-xs text-gray-400">({{ $order->payment_reference }})</span>
                    @endif

                    @if ($order->payment_status === \App\Enums\PaymentStatus::Paid)
                        @if ($order->paid_at)
                            <p class="mt-1 text-xs text-gray-400">{{ __('Paid on') }} {{ $order->paid_at->format('M d, Y g:i A') }}</p>
                        @endif
                        @if ($order->amount_received !== null)
                            <dl class="mt-3 space-y-1 text-sm">
                                @if ($order->currentInvoiceSnapshot)
                                    <div class="flex justify-between">
                                        <dt class="text-gray-500">{{ __('Total Due') }}</dt>
                                        <dd class="text-gray-900">₱{{ number_format($order->currentInvoiceSnapshot->total_amount_due, 2) }}</dd>
                                    </div>
                                @endif
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">{{ __('Amount Received') }}</dt>
                                    <dd class="text-gray-900">₱{{ number_format($order->amount_received, 2) }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">{{ __('Change Due') }}</dt>
                                    <dd class="text-gray-900">₱{{ number_format($order->change_amount, 2) }}</dd>
                                </div>
                            </dl>
                            @if ($order->currentInvoiceSnapshot?->discount_type)
                                <p class="mt-1 text-xs text-[#8A3330] font-medium">
                                    {{ $order->currentInvoiceSnapshot->discount_type->label() }} {{ __('discount') }}: -₱{{ number_format($order->currentInvoiceSnapshot->discount_amount, 2) }}
                                </p>
                            @endif
                        @endif
                        @if ($order->receipt_number)
                            <a href="{{ route('orders.receipt', $order) }}" data-turbo="false" class="mt-3 inline-block text-sm text-[#8A3330] hover:underline font-medium">
                                {{ __('View Receipt') }} ({{ $order->receipt_number }})
                            </a>
                        @endif

                        @if ($totals->hasRefundDue())
                            {{-- Items were cancelled after this invoice was
                                 issued. The invoice is never rewritten; this
                                 is what the cashier owes back. --}}
                            <div class="mt-3 rounded-lg bg-amber-50 border border-amber-300 px-3 py-2.5 text-sm">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-amber-800">{{ __('Adjustment After Payment') }}</p>
                                <dl class="mt-1.5 space-y-1 text-xs">
                                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('Original Subtotal') }}</dt><dd>₱{{ number_format($totals->originalSubtotal, 2) }}</dd></div>
                                    <div class="flex justify-between text-red-600"><dt>{{ __('Cancelled Items') }}</dt><dd>−₱{{ number_format($totals->cancelledAmount, 2) }}</dd></div>
                                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('Active Subtotal') }}</dt><dd>₱{{ number_format($totals->activeSubtotal, 2) }}</dd></div>
                                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('Corrected Total') }}</dt><dd>₱{{ number_format($totals->correctedTotalDue, 2) }}</dd></div>
                                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('Amount Paid') }}</dt><dd>₱{{ number_format($totals->amountPaid, 2) }}</dd></div>
                                </dl>
                                <div class="mt-2 pt-2 border-t border-dashed border-amber-300 flex justify-between font-bold text-amber-800">
                                    <span>{{ __('Refund Due') }}</span>
                                    <span>₱{{ number_format($totals->refundDue, 2) }}</span>
                                </div>
                            </div>
                        @endif

                        <form
                            method="POST"
                            action="{{ route('orders.void-payment', $order) }}"
                            x-data="{ open: false, reason: '' }"
                            @submit.prevent="reason.trim().length > 0 && (open = true)"
                            class="mt-3"
                        >
                            @csrf
                            @method('PATCH')

                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]">
                                {{ __('Void Reason') }}
                            </label>
                            <textarea
                                name="void_reason" x-model="reason" required rows="2"
                                placeholder="{{ __('e.g. Customer cancelled order') }}"
                                class="mt-1 w-full text-sm rounded-lg border-[#E5DDD0] focus:border-red-500 focus:ring-red-500"
                            ></textarea>

                            <button type="submit" class="mt-2 text-sm text-red-600 hover:underline font-medium">
                                {{ __('Void Payment') }}
                            </button>

                            <dialog
                                x-ref="dialog"
                                x-effect="open ? $refs.dialog.showModal() : $refs.dialog.close()"
                                @cancel="open = false"
                                @click="$event.target === $refs.dialog && (open = false)"
                                class="rounded-xl border border-[#E5DDD0] p-0 backdrop:bg-black/40 max-w-sm w-[calc(100%-2rem)] m-auto"
                            >
                                <div class="p-6">
                                    <h3 class="font-semibold text-gray-900">{{ __('Void this payment?') }}</h3>
                                    <p class="mt-2 text-sm text-gray-600">{{ __('This will mark the payment as Voided. The order can be paid again afterwards.') }}</p>
                                    <p class="mt-2 text-sm text-gray-500 italic" x-text="reason"></p>
                                    <div class="mt-6 flex justify-end gap-3">
                                        <button type="button" @click="open = false" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                                            {{ __('Cancel') }}
                                        </button>
                                        <button type="button" @click="open = false; $root.submit()"
                                                class="text-sm font-medium rounded-md px-4 py-2 bg-red-600 hover:bg-red-700 text-white">
                                            {{ __('Void Payment') }}
                                        </button>
                                    </div>
                                </div>
                            </dialog>
                        </form>
                    @else
                        @if ($order->payment_status === \App\Enums\PaymentStatus::Voided)
                            <dl class="text-xs space-y-1">
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Void By') }}</dt><dd class="text-gray-700">{{ $order->voidedBy->name ?? __('Unknown') }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Void Date') }}</dt><dd class="text-gray-700">{{ $order->voided_at?->format('M d, Y g:i A') }}</dd></div>
                                <div class="flex justify-between gap-2"><dt class="text-gray-500 shrink-0">{{ __('Reason') }}</dt><dd class="text-gray-700 text-right">{{ $order->void_reason }}</dd></div>
                            </dl>
                            @if ($order->receipt_number)
                                <a href="{{ route('orders.receipt', $order) }}" data-turbo="false" class="mt-2 inline-block text-sm text-[#8A3330] hover:underline font-medium">
                                    {{ __('View Voided Receipt') }} ({{ $order->receipt_number }})
                                </a>
                            @endif
                            <p class="mt-1 text-xs text-gray-400">{{ __('You can accept payment again below.') }}</p>
                        @endif

                        @include('orders.partials.checkout-form')
                    @endif
                </div>

                <div class="pt-4 border-t border-dashed border-[#D9CCBA] space-y-3">
                    @if ($order->customer_name)
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Customer') }}</p>
                            <p class="text-sm text-gray-700">{{ $order->customer_name }}</p>
                        </div>
                    @endif
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Created By') }}</p>
                        <p class="text-sm text-gray-700">{{ $order->creator->name ?? __('Self-Order (QR)') }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Created At') }}</p>
                        <p class="text-sm text-gray-700">{{ $order->created_at->format('M d, Y g:i A') }}</p>
                    </div>
                </div>
            </div>

            @if ($order->spaceSession && $order->spaceSession->orders->count() > 1)
                {{-- All guest order batches under this table's dining session --}}
                <div class="bg-white border border-[#E5DDD0] rounded-xl p-6">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Table Session') }}</p>
                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full {{ $order->spaceSession->isActive() ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $order->spaceSession->isActive() ? __('Active') : __('Closed') }}
                        </span>
                    </div>
                    <div class="mt-3 space-y-2">
                        @foreach ($order->spaceSession->orders->sortBy('batch_number') as $sessionOrder)
                            <a href="{{ $sessionOrder->id === $order->id ? '#' : route('orders.show', $sessionOrder) }}"
                               class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm {{ $sessionOrder->id === $order->id ? 'border-[#8A3330] bg-[#FAF6EE]' : 'border-[#E5DDD0] hover:border-[#8A3330]' }}">
                                <span class="min-w-0">
                                    <span class="font-medium text-gray-900">{{ __('Batch') }} #{{ $sessionOrder->batch_number ?? '—' }}</span>
                                    <span class="block text-xs text-gray-500 truncate">
                                        {{ $sessionOrder->guestSession?->displayLabel() ?? __('Staff') }}
                                        · {{ $sessionOrder->status->label() }}
                                        · {{ $sessionOrder->payment_status->label() }}
                                    </span>
                                </span>
                                <span class="shrink-0 text-sm font-semibold text-gray-900">₱{{ number_format($sessionOrder->total_amount, 2) }}</span>
                            </a>
                        @endforeach
                    </div>
                    <div class="mt-3 pt-3 border-t border-dashed border-[#D9CCBA] flex items-center justify-between text-sm">
                        <span class="font-semibold text-gray-900">{{ __('Combined Table Total') }}</span>
                        <span class="font-bold text-[#8A3330]">₱{{ number_format($order->spaceSession->orders->sum(fn ($o) => (float) $o->total_amount), 2) }}</span>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
