<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between"
             x-data="{ connected: false }"
             x-init="
                // Pusher usually finishes connecting before this handler binds,
                // so 'connected' (fired once, on the transition) has already
                // come and gone — read the current state directly too, or the
                // dot sits gray forever even though the socket is fine.
                connected = Echo.connector.pusher.connection.state === 'connected';
                const onConnected = () => connected = true;
                const onDisconnected = () => connected = false;
                const onUnavailable = () => connected = false;
                Echo.private('kitchen').listen('.KitchenUpdated', () => window.location.reload());
                Echo.connector.pusher.connection.bind('connected', onConnected);
                Echo.connector.pusher.connection.bind('disconnected', onDisconnected);
                Echo.connector.pusher.connection.bind('unavailable', onUnavailable);
                turboCleanup(() => {
                    Echo.leave('kitchen');
                    Echo.connector.pusher.connection.unbind('connected', onConnected);
                    Echo.connector.pusher.connection.unbind('disconnected', onDisconnected);
                    Echo.connector.pusher.connection.unbind('unavailable', onUnavailable);
                });
             ">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight flex items-center gap-2">
                    {{ __('Kitchen Display') }}
                    <span class="h-2 w-2 rounded-full" :class="connected ? 'bg-green-500' : 'bg-gray-300'" :title="connected ? '{{ __('Live') }}' : '{{ __('Reconnecting…') }}'"></span>
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">{{ __('Real-time order status') }}</p>
            </div>
            <a href="{{ route('kitchen.index') }}" class="text-sm text-[#8A3330] hover:underline font-medium">
                {{ __('Refresh') }}
            </a>
        </div>
    </x-slot>

    {{-- No polling reload here — the header's Echo listener already reloads
         on a real .KitchenUpdated event, and pusher-js reconnects on its own
         after a network drop. A blind reload every 60s was tearing down and
         re-establishing that WebSocket connection every minute, which is
         exactly what made the "Live" dot above keep flickering back to gray. --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        {{-- New Orders --}}
        <div>
            <div class="flex items-center justify-between pb-3 mb-4 border-b-2 border-amber-400">
                <h3 class="text-sm font-bold uppercase tracking-wide text-gray-900">{{ __('New Orders') }}</h3>
                <span class="inline-flex items-center justify-center min-w-[24px] h-6 px-2 rounded-full bg-amber-100 text-amber-700 text-xs font-bold">{{ $pending->count() }}</span>
            </div>
            <div class="space-y-4">
                @forelse ($pending as $order)
                    @include('kitchen.partials.order-card', [
                        'order' => $order,
                        'nextStatus' => 'preparing',
                        'buttonLabel' => __('Start Preparing'),
                        'accentColor' => 'amber',
                    ])
                @empty
                    <p class="text-sm text-gray-400 text-center py-10 border border-dashed border-[#D9CCBA] rounded-xl">{{ __('No pending orders.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- In Progress --}}
        <div>
            <div class="flex items-center justify-between pb-3 mb-4 border-b-2 border-blue-400">
                <h3 class="text-sm font-bold uppercase tracking-wide text-gray-900">{{ __('In Progress') }}</h3>
                <span class="inline-flex items-center justify-center min-w-[24px] h-6 px-2 rounded-full bg-blue-100 text-blue-700 text-xs font-bold">{{ $preparing->count() }}</span>
            </div>
            <div class="space-y-4">
                @forelse ($preparing as $order)
                    @include('kitchen.partials.order-card', [
                        'order' => $order,
                        'nextStatus' => 'ready',
                        'buttonLabel' => __('Mark Ready'),
                        'accentColor' => 'blue',
                    ])
                @empty
                    <p class="text-sm text-gray-400 text-center py-10 border border-dashed border-[#D9CCBA] rounded-xl">{{ __('Nothing being prepared.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Ready --}}
        <div>
            <div class="flex items-center justify-between pb-3 mb-4 border-b-2 border-purple-400">
                <h3 class="text-sm font-bold uppercase tracking-wide text-gray-900">{{ __('Ready') }}</h3>
                <span class="inline-flex items-center justify-center min-w-[24px] h-6 px-2 rounded-full bg-purple-100 text-purple-700 text-xs font-bold">{{ $ready->count() }}</span>
            </div>
            <div class="space-y-4">
                @forelse ($ready as $order)
                    @include('kitchen.partials.order-card', [
                        'order' => $order,
                        'nextStatus' => 'served',
                        'buttonLabel' => __('Mark Served'),
                        'accentColor' => 'purple',
                    ])
                @empty
                    <p class="text-sm text-gray-400 text-center py-10 border border-dashed border-[#D9CCBA] rounded-xl">{{ __('Nothing ready yet.') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    <p class="flex items-center gap-1.5 text-xs text-gray-400 mt-8">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
        </svg>
        {{ __('Orders update in real-time.') }}
    </p>
</x-app-layout>
