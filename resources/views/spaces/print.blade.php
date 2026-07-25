<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Print QR') }} — {{ $space->name }}</title>
        <style>
            * { box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', Arial, sans-serif;
                background: #F7F0E3;
                margin: 0;
                padding: 40px 20px;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            .toolbar {
                margin-bottom: 24px;
            }
            .toolbar button, .toolbar a {
                display: inline-block;
                font-size: 14px;
                font-weight: 600;
                padding: 10px 20px;
                border-radius: 8px;
                border: 1px solid #D9CCBA;
                background: #8A3330;
                color: #fff;
                text-decoration: none;
                cursor: pointer;
                margin-right: 8px;
            }
            .toolbar a.secondary {
                background: #fff;
                color: #333;
            }
            .card {
                width: 340px;
                max-width: 100%;
                background: #fff;
                border: 2px solid #8A3330;
                border-radius: 16px;
                padding: 32px 24px;
                text-align: center;
            }
            .card .resort-name {
                font-size: 15px;
                font-weight: 700;
                color: #3A2E28;
                letter-spacing: 0.5px;
                text-transform: uppercase;
            }
            .card .instruction {
                font-size: 13px;
                color: #6F6258;
                margin-top: 4px;
            }
            .card img {
                width: 220px;
                max-width: 100%;
                height: auto;
                aspect-ratio: 1 / 1;
                margin: 24px auto;
                display: block;
            }
            .card .space-name {
                font-size: 24px;
                font-weight: 700;
                color: #8A3330;
                border-top: 1px dashed #D9CCBA;
                padding-top: 16px;
            }

            @media print {
                body { background: #fff; padding: 0; }
                .toolbar { display: none; }
                .card { border: 2px solid #000; }
            }
        </style>
    </head>
    <body>
        <div class="toolbar">
            <button onclick="window.print()">{{ __('Print') }}</button>
            <a href="{{ route('spaces.index', ['area' => $space->area_id]) }}" data-turbo="false" class="secondary">{{ __('Back') }}</a>
        </div>

        <div class="card">
            <div class="resort-name">88 Hot Spring Resort</div>
            <div class="instruction">{{ __('Scan to view menu & order') }}</div>
            <img src="{{ route('spaces.qr-code', $space) }}" alt="{{ __('QR code for') }} {{ $space->name }}">
            <div class="space-name">{{ $space->name }}</div>
        </div>
    </body>
</html>
