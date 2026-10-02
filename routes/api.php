<?php

use App\Http\Controllers\Api\PrinterJobController;
use App\Http\Controllers\MayaCheckoutController;
use Illuminate\Support\Facades\Route;

Route::middleware('printer-bridge')->prefix('printer-jobs')->group(function () {
    Route::get('/', [PrinterJobController::class, 'index'])->name('api.printer-jobs.index');
    Route::post('/{printerJob}/ack', [PrinterJobController::class, 'ack'])->name('api.printer-jobs.ack');
});

// Maya Checkout webhook. Unauthenticated by nature — the controller only uses
// the body to find the payment and re-reads its status from Maya's API.
Route::post('webhooks/maya', [MayaCheckoutController::class, 'webhook'])
    ->middleware('throttle:120,1')
    ->name('api.webhooks.maya');
