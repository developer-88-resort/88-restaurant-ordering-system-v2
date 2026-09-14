<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Receipt') }} {{ $order->receipt_number }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
        }
    </style>
</head>
<body class="font-sans text-gray-900 antialiased bg-[#F7F0E3] min-h-screen py-10 px-4">
    <div class="max-w-sm mx-auto">
        <div class="no-print flex items-center justify-between mb-4">
            <a href="{{ route('customer.orders.status', $order->public_token) }}" data-turbo="false" class="text-sm text-[#8A3330] hover:underline font-medium">
                &larr; {{ __('Back to Order') }}
            </a>
            <button onclick="window.print()" class="text-sm font-medium rounded-md px-3 py-1.5 bg-[#8A3330] hover:bg-[#742927] text-white">
                {{ __('Print') }}
            </button>
        </div>

        @include('orders.partials.receipt-body', ['order' => $order])
    </div>
</body>
</html>
