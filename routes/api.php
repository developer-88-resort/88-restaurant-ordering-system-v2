<?php

use App\Http\Controllers\Api\PrinterJobController;
use Illuminate\Support\Facades\Route;

// The maintenance page asks this every few seconds and comes back on its own
// once Maintenance Mode is off. Here, not in web.php, so no session, PIN or
// idle sign-out check can answer in its place. Just on/off, nothing else.
Route::get('/maintenance/check', fn () => response()->json(['on' => \App\Models\Setting::maintenanceIsOn()])
    ->header('Cache-Control', 'no-store'))->name('maintenance.check');

Route::middleware('printer-bridge')->prefix('printer-jobs')->group(function () {
    Route::get('/', [PrinterJobController::class, 'index'])->name('api.printer-jobs.index');
    Route::post('/{printerJob}/ack', [PrinterJobController::class, 'ack'])->name('api.printer-jobs.ack');
});
