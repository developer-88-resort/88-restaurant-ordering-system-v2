<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Thermal (ESC/POS) Printer
    |--------------------------------------------------------------------------
    |
    | Connection details for a network ESC/POS thermal printer (kitchen slip
    | / receipt printer), used by App\Services\Printing\ThermalPrinterService.
    | Port 9100 is the standard raw-socket port these printers listen on.
    |
    | THERMAL_PRINTER_HOST takes a comma-separated list, because the printer
    | carries a static IP between two sites on different subnets — .1.50 here,
    | .0.50 at the restaurant. Every address listed is tried until one
    | answers, so the same .env works at both without editing.
    |
    */

    'thermal' => [
        'hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('THERMAL_PRINTER_HOST', '192.168.1.50')),
        ))),
        'port' => env('THERMAL_PRINTER_PORT', 9100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Printer Bridge
    |--------------------------------------------------------------------------
    |
    | The production server (Hostinger VPS) has no route to the printer's
    | LAN IP, so print jobs are queued in the `printer_jobs` table and
    | picked up by a `printer:bridge` process running on a PC inside the
    | resort's own network. These two endpoints are how that bridge talks
    | to the server — `bridge_token` is a shared secret (NOT a user
    | account), checked by VerifyPrinterBridgeToken. Must match on both
    | ends: this app's .env here, and the bridge machine's .env.
    |
    */

    'bridge_token' => env('PRINTER_BRIDGE_TOKEN'),

    // A job handed to a bridge that never acknowledged it (the PC slept,
    // Chrome hung) is offered again after this many seconds.
    'claim_timeout_seconds' => (int) env('PRINTER_JOB_CLAIM_TIMEOUT', 120),

    // Only read by the printer:bridge command (the local PC's own .env) —
    // the production server never calls out to itself with this.
    'bridge_api_url' => env('PRINTER_BRIDGE_API_URL'),

    // How many copies one Direct Print press puts out — the same for every
    // slip that reaches the kitchen, whether it came from an advance order,
    // a walk-in, a QR order or a mix of them. Decided on the server and
    // carried in the job, so every bridge prints the same thing.
    'kitchen_slip_copies' => (int) env('KITCHEN_SLIP_COPIES', 3),

    // With a pause, each copy is its own slip, cut, and the printer rests
    // this long before the next one — time to take the previous slip off.
    // Set it to 0 and the copies come out as one continuous length of paper
    // with a marked tear line between them instead.
    'copy_pause_seconds' => (int) env('SLIP_COPY_PAUSE_SECONDS', 3),

    /*
    |--------------------------------------------------------------------------
    | Slip Image Rendering
    |--------------------------------------------------------------------------
    |
    | The bridge screenshots each slip's HTML with headless Chrome and prints
    | the picture, so Direct Print matches the browser Print button exactly.
    | Leave the binary blank to auto-detect the usual install locations; set
    | it when Chrome lives somewhere unusual. Only the bridge machine needs
    | this — the production server never renders images.
    |
    */

    'chrome_binary' => env('CHROME_BINARY'),

    'image_timeout' => env('SLIP_IMAGE_TIMEOUT', 30),

];
