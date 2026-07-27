<?php

return [

    // Default thermal paper width used by the print-optimized receipt view
    // (resources/views/receipts/print.blade.php) when no ?paper= query
    // param is given. Client's printer model wasn't known at build time,
    // so this stays overridable per-request until it settles.
    'default_paper_width' => env('RECEIPT_PAPER_WIDTH', '80mm'),

];
