<x-app-layout>
    <x-slot name="header">
        <div class="relative rounded-2xl border border-slate-200 bg-white px-6 py-7 shadow-sm sm:pl-24">
            <span class="mb-4 grid h-12 w-12 place-items-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-600 sm:absolute sm:left-6 sm:top-7 sm:mb-0" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M5 21V4a1 1 0 0 1 1-1h12v18M3 21h18M9 3v18m4-9h.01M12 6h3" /></svg></span>
            <h2 class="font-semibold text-2xl tracking-tight text-slate-900 leading-tight">{{ __('Rooms') }}</h2>
            <p class="max-w-4xl text-sm leading-6 text-slate-500 mt-2">{{ __('The guest rooms a Room Charge can go on, numbered and typed the same as the front desk system. Switch a room off instead of deleting it — past room charges still point at it.') }}</p>
        </div>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        {{-- Rooms, grouped by type in front desk order --}}
        <div class="lg:col-span-2 space-y-5">
            @foreach ($types as $type)
                @php $rooms = $roomsByType->get($type->id, collect()); @endphp
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50/70 px-5 py-4">
                        <h3 class="text-base font-semibold text-slate-900">{{ $type->code }} <span class="font-medium text-slate-500">· {{ $type->name }}</span></h3>
                        <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium tabular-nums text-slate-500">
                            {{ trans_choice(':count room|:count rooms', $rooms->where('active', true)->count(), ['count' => $rooms->where('active', true)->count()]) }}
                            @if ($rooms->where('active', false)->isNotEmpty())
                                · {{ $rooms->where('active', false)->count() }} {{ __('off') }}
                            @endif
                        </span>
                    </div>

                    @if ($rooms->isEmpty())
                        <p class="px-5 py-4 text-sm text-slate-400">{{ __('No rooms of this type.') }}</p>
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($rooms as $room)
                                <li class="flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-3.5 transition-colors hover:bg-slate-50/60 {{ $room->active ? '' : 'bg-slate-50' }}">
                                    <span class="w-24 text-sm font-semibold tabular-nums {{ $room->active ? 'text-slate-900' : 'text-slate-400 line-through' }}">{{ $room->label() }}</span>

                                    <form method="POST" action="{{ route('superadmin.rooms.update', $room) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <label class="sr-only" for="type-{{ $room->id }}">{{ __('Type') }}</label>
                                        <select id="type-{{ $room->id }}" name="room_type_id" onchange="this.form.submit()"
                                                class="min-h-10 bg-white text-xs rounded-xl border-slate-200 py-2 focus:border-slate-400 focus:ring-slate-200">
                                            @foreach ($types as $option)
                                                <option value="{{ $option->id }}" @selected($option->id === $room->room_type_id)>{{ $option->code }} — {{ $option->name }}</option>
                                            @endforeach
                                        </select>
                                    </form>

                                    <form method="POST" action="{{ route('superadmin.rooms.update', $room) }}" class="flex items-center gap-1.5">
                                        @csrf
                                        @method('PATCH')
                                        <label class="text-[11px] text-slate-500" for="sort-{{ $room->id }}">{{ __('Order') }}</label>
                                        <input id="sort-{{ $room->id }}" type="number" name="sort_order" value="{{ $room->sort_order }}" min="0"
                                               onchange="this.form.submit()"
                                               class="w-20 text-xs rounded-lg border-slate-200 py-1.5 focus:border-slate-400 focus:ring-slate-200">
                                    </form>

                                    <form method="POST" action="{{ route('superadmin.rooms.update', $room) }}" class="ml-auto">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="active" value="{{ $room->active ? 0 : 1 }}">
                                        <button type="submit"
                                                class="inline-flex min-h-10 items-center justify-center rounded-xl border px-3 py-2 text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 {{ $room->active ? 'border-slate-200 bg-white text-slate-600 hover:border-red-200 hover:bg-red-50 hover:text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                            {{ $room->active ? __('Switch off') : __('Switch on') }}
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="space-y-5 lg:sticky lg:top-24">
            {{-- Add a room --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
                <h3 class="text-base font-semibold text-slate-900">{{ __('Add a room') }}</h3>
                <form method="POST" action="{{ route('superadmin.rooms.store') }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label for="room_no" class="block text-xs font-medium text-slate-600">{{ __('Room no.') }}</label>
                        <input id="room_no" name="room_no" value="{{ old('room_no') }}" required maxlength="20" placeholder="e.g. 518"
                               class="mt-2 min-h-11 w-full bg-white text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                        @error('room_no', 'createRoom') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="room_type_id" class="block text-xs font-medium text-slate-600">{{ __('Type') }}</label>
                        <select id="room_type_id" name="room_type_id" required
                                class="mt-2 min-h-11 w-full bg-white text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                            @foreach ($types as $type)
                                <option value="{{ $type->id }}" @selected((int) old('room_type_id') === $type->id)>{{ $type->code }} — {{ $type->name }}</option>
                            @endforeach
                        </select>
                        @error('room_type_id', 'createRoom') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="sort_order" class="block text-xs font-medium text-slate-600">{{ __('Order (optional)') }}</label>
                        <input id="sort_order" type="number" name="sort_order" value="{{ old('sort_order') }}" min="0"
                               class="mt-2 min-h-11 w-full bg-white text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                        <p class="mt-1 text-[11px] text-slate-400">{{ __('Left blank, the room goes after the others.') }}</p>
                    </div>
                    <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-slate-800 px-4 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">{{ __('Add room') }}</button>
                </form>
            </div>

            {{-- Receipt option --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
                <h3 class="text-base font-semibold text-slate-900">{{ __('Room charge receipts') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('Each room charge prints an authorization block for the guest to sign. With a front desk copy, it prints twice with a cut line: the outlet keeps one, front desk posts from the other.') }}</p>
                <form method="POST" action="{{ route('superadmin.rooms.front-desk-copy') }}" class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="room_charge_front_desk_copy" value="{{ $frontDeskCopy ? 0 : 1 }}">
                    <span class="text-sm font-medium text-slate-700">{{ __('Print a front desk copy') }}: <strong>{{ $frontDeskCopy ? __('On') : __('Off') }}</strong></span>
                    <button type="submit" class="min-h-10 shrink-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                        {{ $frontDeskCopy ? __('Turn off') : __('Turn on') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
