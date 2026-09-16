<?php

namespace App\Http\Controllers\Api;

use App\Enums\PrinterJobStatus;
use App\Http\Controllers\Controller;
use App\Models\PrinterJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Polled by the `printer:bridge` process running on the resort's local
 * network — see config/printing.php's "Printer Bridge" block for why this
 * is a poll/ack queue rather than a live broadcast.
 */
class PrinterJobController extends Controller
{
    public function index(): JsonResponse
    {
        $jobs = PrinterJob::query()
            ->where('status', PrinterJobStatus::Pending)
            ->oldest()
            ->limit(20)
            ->get(['id', 'type', 'payload']);

        return response()->json(['jobs' => $jobs]);
    }

    public function ack(Request $request, PrinterJob $printerJob): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:printed,failed'],
            'error_message' => ['nullable', 'string', 'max:2000'],
        ]);

        $printerJob->update([
            'status' => $validated['status'],
            'attempts' => $printerJob->attempts + 1,
            'error_message' => $validated['status'] === 'failed' ? ($validated['error_message'] ?? null) : null,
            'printed_at' => $validated['status'] === 'printed' ? now() : null,
        ]);

        return response()->json(['ok' => true]);
    }
}
