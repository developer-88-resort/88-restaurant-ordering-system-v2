<?php

namespace App\Services\Printing;

use App\Enums\PrinterJobStatus;
use App\Models\Order;
use App\Models\PrinterJob;
use Illuminate\Support\Facades\DB;

/**
 * Puts a kitchen slip on the printer_jobs queue for the LAN bridge to print
 * (see config/printing.php's "Printer Bridge" block).
 *
 * Only ever from the Kitchen Display's Direct Print button — placing an
 * order never prints on its own; the kitchen decides when a slip goes to
 * paper.
 */
class KitchenSlipQueue
{
    /**
     * One slip per press: while this order already has a job waiting or on
     * the printer, that same job is handed back instead of queueing another
     * copy. `wasRecentlyCreated` on the returned job tells the two apart.
     */
    public static function queue(Order $order): PrinterJob
    {
        return DB::transaction(function () use ($order) {
            $waiting = PrinterJob::where('order_id', $order->id)
                ->where('type', 'kitchen_slip')
                ->whereIn('status', [PrinterJobStatus::Pending, PrinterJobStatus::Printing])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($waiting) {
                return $waiting;
            }

            $order->load(['area', 'spaceCategory', 'space', 'creator', 'guestSession', 'sourceQuotation', 'items.adjustments', 'items.cookingStyle']);

            return PrinterJob::create([
                'type' => 'kitchen_slip',
                'order_id' => $order->id,
                'payload' => [
                    ...KitchenSlipPayloadBuilder::build($order),
                    // How many slips this one press puts out, and how long the
                    // printer rests between them — settled here so every
                    // bridge, old or new, prints the same thing.
                    'copies' => self::copies(),
                    'copy_pause_seconds' => (int) config('printing.copy_pause_seconds', 3),
                ],
                // Spelled out rather than left to the column default, so the
                // job handed back to the Kitchen Display knows its own status.
                'status' => PrinterJobStatus::Pending,
            ]);
        });
    }

    /**
     * A slip is needed in more than one pair of hands, so one press prints
     * several copies — the same for every slip that reaches the kitchen,
     * however the order got there.
     */
    public static function copies(): int
    {
        return max(1, (int) config('printing.kitchen_slip_copies', 3));
    }

    /**
     * The slip this order currently has waiting or on the printer, if any —
     * what the Kitchen Display uses to keep Direct Print locked across a
     * board refresh.
     *
     * @param  array<int, int>  $orderIds
     * @return array<int, int> order id => printer job id
     */
    public static function activeJobsFor(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        return PrinterJob::whereIn('order_id', $orderIds)
            ->where('type', 'kitchen_slip')
            ->whereIn('status', [PrinterJobStatus::Pending, PrinterJobStatus::Printing])
            ->orderBy('id')
            ->pluck('id', 'order_id')
            ->all();
    }
}
