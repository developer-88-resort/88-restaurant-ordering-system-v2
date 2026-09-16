<?php

use App\Http\Controllers\Api\PrinterJobController;
use Illuminate\Support\Facades\Route;

Route::middleware('printer-bridge')->prefix('printer-jobs')->group(function () {
    Route::get('/', [PrinterJobController::class, 'index'])->name('api.printer-jobs.index');
    Route::post('/{printerJob}/ack', [PrinterJobController::class, 'ack'])->name('api.printer-jobs.ack');
});
