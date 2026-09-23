<?php

namespace App\Http\Controllers\Api;

use App\Enums\PrinterJobStatus;
use App\Http\Controllers\Controller;
use App\Models\PrinterJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Polled by the `printer:bridge` process running on the resort's local
 * network — see config/printing.php's "Printer Bridge" block for why this
 * is a poll/ack queue rather than a live broadcast.
 */
class PrinterJobController extends Controller
{
    /**
     * Hands out the next jobs and claims them in the same step, so each job
     * goes to exactly one bridge — two bridges polling at once used to both
     * receive (and print) every slip. A claim that was never acknowledged
     * within the timeout is offered again rather than lost. The response
     * shape is unchanged, so a bridge running older code keeps working.
     */
    public function index(): JsonResponse
    {
        $jobs = DB::transaction(function () {
            $staleBefore = now()->subSeconds((int) config('printing.claim_timeout_seconds', 120));

            $jobs = PrinterJob::query()
                ->where(fn ($query) => $query
                    ->where('status', PrinterJobStatus::Pending)
                    ->orWhere(fn ($query) => $query
                        ->where('status', PrinterJobStatus::Printing)
                        ->where('claimed_at', '<', $staleBefore)))
                ->oldest()
                ->oldest('id')
                ->limit(20)
                ->lockForUpdate()
                ->get(['id', 'type', 'payload']);

            if ($jobs->isNotEmpty()) {
                PrinterJob::whereKey($jobs->modelKeys())->update([
                    'status' => PrinterJobStatus::Printing,
                    'claimed_at' => now(),
                ]);
            }

            return $jobs;
        });

        return response()->json(['jobs' => $jobs]);
    }

    public function ack(Request $request, PrinterJob $printerJob): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:printed,failed'],
            'error_message' => ['nullable', 'string', 'max:2000'],
        ]);

        // A slip that already reached paper stays printed: a late or repeated
        // acknowledgement (the bridge retrying one it couldn't deliver) must
        // never flip it back into something the kitchen would print again.
        if ($printerJob->status === PrinterJobStatus::Printed) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $printerJob->update([
            'status' => $validated['status'],
            'attempts' => $printerJob->attempts + 1,
            'error_message' => $validated['status'] === 'failed' ? ($validated['error_message'] ?? null) : null,
            'printed_at' => $validated['status'] === 'printed' ? now() : null,
        ]);

        return response()->json(['ok' => true]);
    }
}
