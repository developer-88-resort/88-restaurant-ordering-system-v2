{{--
    The order detail + actions, bound to the shared `selectedOrder` Alpine
    state on the page's outer wrapper. Included twice: once inside the
    always-visible desktop side column, once inside the mobile bottom
    sheet — so both stay in sync automatically instead of being
    hand-duplicated.
--}}

{{-- Empty state --}}
<template x-if="!selectedOrder">

    <div class="flex h-full min-h-[500px] flex-col items-center justify-center px-6 text-center">

        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
            <svg
                class="h-5 w-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.8"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9 12h6M9 16h6M7 4h10a2 2 0 012 2v14H5V6a2 2 0 012-2z"
                />
            </svg>
        </div>

        <p class="mt-3 text-[16px] font-semibold text-slate-700">
            {{ __('Select an order') }}
        </p>

        <p class="mt-1 text-[14px] leading-5 text-slate-400">
            {{ __('Click an order card to view details and actions.') }}
        </p>

    </div>

</template>


{{-- Selected --}}
<template x-if="selectedOrder">

    <div>

        {{-- DETAILS HEADER --}}
        <div class="border-b border-slate-200 p-4">

            <div class="flex items-start justify-between gap-3">

                <div>
                    <p class="text-[13px] font-bold uppercase tracking-wider text-slate-400">
                        {{ __('Order') }}
                    </p>

                    <h3
                        class="mt-0.5 text-lg font-bold text-slate-900"
                        x-text="selectedOrder.number"
                    ></h3>
                </div>

                <span
                    class="rounded-md px-2 py-1 text-[13px] font-bold uppercase"
                    :class="{
                        'bg-blue-50 text-blue-600':
                            selectedOrder.statusColor === 'blue',

                        'bg-orange-50 text-orange-600':
                            selectedOrder.statusColor === 'orange',

                        'bg-green-50 text-green-600':
                            selectedOrder.statusColor === 'green',

                        'bg-violet-50 text-violet-600':
                            selectedOrder.statusColor === 'violet'
                    }"

                    x-text="selectedOrder.status"
                ></span>

            </div>


            <div class="mt-4 space-y-2 text-[14px] text-slate-600">

                <div class="flex items-center gap-2">

                    <svg
                        class="h-4 w-4 text-slate-400"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.7"
                            d="M5 5h14v14H5z"
                        />
                    </svg>

                    <span x-text="selectedOrder.type"></span>

                </div>

                <div class="flex items-center gap-2">

                    <svg
                        class="h-4 w-4 text-slate-400"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.7"
                            d="M4 20V8l8-4 8 4v12"
                        />
                    </svg>

                    <span x-text="selectedOrder.location"></span>

                </div>

                <template x-if="selectedOrder.guest">

                    <div class="flex items-center gap-2">

                        <svg
                            class="h-4 w-4 text-slate-400"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <circle
                                cx="12"
                                cy="8"
                                r="3"
                            />

                            <path d="M6 20a6 6 0 0112 0" />
                        </svg>

                        <span x-text="selectedOrder.guest"></span>

                    </div>

                </template>

                <template x-if="selectedOrder.customer">

                    <div class="flex items-center gap-2">

                        <svg
                            class="h-4 w-4 text-slate-400"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <circle
                                cx="12"
                                cy="8"
                                r="3"
                            />

                            <path d="M6 20a6 6 0 0112 0" />
                        </svg>

                        <span>
                            {{ __('By:') }}

                            <strong
                                class="font-semibold"
                                x-text="selectedOrder.customer"
                            ></strong>
                        </span>

                    </div>

                </template>

                <div class="flex items-center gap-2">

                    <svg
                        class="h-4 w-4 text-slate-400"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <circle
                            cx="12"
                            cy="12"
                            r="8"
                        />

                        <path d="M12 8v4l3 2" />
                    </svg>

                    <span x-text="selectedOrder.createdAt"></span>

                </div>

            </div>
        </div>


        {{-- ITEMS --}}
        <div class="border-b border-slate-200 p-4">

            <p class="mb-3 text-[13px] font-bold uppercase tracking-wider text-slate-500">
                {{ __('Items') }}
            </p>

            <div class="space-y-3">

                <template
                    x-for="(item, index) in selectedOrder.items"
                    :key="index"
                >

                    <div
                        class="flex items-start gap-2 text-[14px]"
                        :class="item.cancelled
                            ? 'opacity-40'
                            : ''"
                    >

                        <span
                            class="min-w-[35px] font-semibold text-slate-500"
                            x-text="item.quantity"
                        ></span>

                        <div class="min-w-0 flex-1">

                            <div class="flex flex-wrap items-center gap-1">

                                <span
                                    class="font-medium text-slate-800"
                                    :class="item.cancelled
                                        ? 'line-through'
                                        : ''"
                                    x-text="item.name"
                                ></span>

                                <template x-if="item.cookingLabel">

                                    <span
                                        class="rounded bg-teal-50 px-1.5 py-0.5 text-[12px] font-bold uppercase text-teal-700"
                                        x-text="item.cookingLabel"
                                    ></span>

                                </template>

                            </div>

                            <template x-if="item.cookingNote">

                                <p
                                    class="mt-1 text-[13px] font-medium text-teal-600"
                                    x-text="item.cookingNote"
                                ></p>

                            </template>

                            <template x-if="item.notes">

                                <p
                                    class="mt-1 text-[13px] text-slate-400"
                                    x-text="item.notes"
                                ></p>

                            </template>

                        </div>
                    </div>

                </template>

            </div>
        </div>


        {{-- ORDER NOTE --}}
        <template x-if="selectedOrder.notes">

            <div class="border-b border-slate-200 p-4">

                <p class="text-[13px] font-bold uppercase tracking-wider text-slate-500">
                    {{ __('Notes') }}
                </p>

                <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 p-2.5">

                    <p
                        class="text-[14px] leading-5 text-amber-900"
                        x-text="selectedOrder.notes"
                    ></p>

                </div>

            </div>

        </template>


        {{-- SCHEDULE --}}
        <template x-if="selectedOrder.scheduledFor">

            <div class="border-b border-slate-200 p-4">

                <p class="text-[13px] font-bold uppercase tracking-wider text-slate-500">
                    {{ __('Schedule') }}
                </p>

                <div class="mt-2 rounded-lg border border-violet-200 bg-violet-50 p-3">

                    <p class="text-[13px] font-semibold text-violet-500">
                        {{ __('Scheduled preparation') }}
                    </p>

                    <p
                        class="mt-1 text-[16px] font-bold text-violet-700"
                        x-text="selectedOrder.scheduledFor"
                    ></p>

                </div>

            </div>

        </template>


        {{-- ACTIONS --}}
        <template x-if="selectedOrder.nextStatus">

            <div class="space-y-2 p-4">

                {{-- Main status action --}}
                <form
                    method="POST"
                    x-bind:action="selectedOrder.actionUrl"
                >
                    @csrf
                    @method('PATCH')

                    <input
                        type="hidden"
                        name="status"
                        x-bind:value="selectedOrder.nextStatus"
                    >

                    <button
                        type="submit"
                        class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 text-[14px] font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-blue-700"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="m9 6 7 6-7 6V6z"
                            />
                        </svg>

                        <span x-text="selectedOrder.buttonLabel"></span>

                    </button>
                </form>


                {{-- Cancel --}}
                <form
                    method="POST"
                    x-bind:action="selectedOrder.actionUrl"

                    x-on:submit="
                        if (!confirm(
                            '{{ __('Cancel this order? This cannot be undone.') }}'
                        )) {
                            $event.preventDefault();
                        }
                    "
                >
                    @csrf
                    @method('PATCH')

                    <input
                        type="hidden"
                        name="status"
                        value="cancelled"
                    >

                    <button
                        type="submit"
                        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-red-200 bg-white text-[14px] font-bold uppercase tracking-wide text-red-600 transition hover:bg-red-50"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M6 7h12M9 7V4h6v3m-7 0 .7 13h6.6L16 7"
                            />
                        </svg>

                        {{ __('Cancel Order') }}

                    </button>
                </form>

            </div>

        </template>


        {{-- Scheduled notice --}}
        <template
            x-if="
                !selectedOrder.nextStatus &&
                selectedOrder.scheduledFor
            "
        >

            <div class="p-4">

                <div class="rounded-lg border border-violet-200 bg-violet-50 p-3 text-center">

                    <p class="text-[14px] leading-5 text-violet-700">
                        {{ __('This order is not cookable yet. It will move automatically to the active board at its scheduled time.') }}
                    </p>

                </div>

            </div>

        </template>

    </div>

</template>
