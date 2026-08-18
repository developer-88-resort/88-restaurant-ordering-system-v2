<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One-time cleanup for tables that ended up with more than one open order
 * before the shared resolver (OrderAppender::resolveOrder()) existed to
 * prevent that. Every donor order's items move onto the table's oldest
 * open order (the "primary") and the donor is marked Cancelled — never
 * deleted, its history stays queryable, it just stops being billable.
 *
 * Dry-run by default: prints exactly what would happen and writes nothing.
 * Pass --apply to actually perform the merge.
 */
class MergeDuplicateReceipts extends Command
{
    protected $signature = 'orders:merge-duplicate-receipts {--apply : Actually perform the merge. Without this flag, only a report is printed.}';

    protected $description = 'Find tables with more than one currently-open order and merge them into one receipt.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $candidates = Order::query()
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Completed])
            ->where('payment_status', '!=', PaymentStatus::Paid)
            // Never sweep a future-dated reservation into this — it must
            // stay its own standalone order exactly like the live resolver
            // treats it.
            ->whereDoesntHave('sourceQuotation', fn ($q) => $q->where('scheduled_for', '>', now()))
            ->whereNotNull('space_id')
            ->with(['items', 'space'])
            ->oldest('id')
            ->get();

        $groups = $candidates->groupBy('space_id')->filter(fn (Collection $group) => $group->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('No duplicate open receipts found — nothing to merge.');

            return self::SUCCESS;
        }

        $this->line($apply
            ? 'Applying merges...'
            : 'DRY RUN -- no changes will be written. Pass --apply to actually merge.');
        $this->newLine();

        foreach ($groups as $spaceId => $orders) {
            $primary = $orders->first();
            $donors = $orders->slice(1);
            $spaceName = $primary->space->name ?? "Space #{$spaceId}";

            $this->line("Table: {$spaceName} — {$orders->count()} open orders found");
            $this->line(sprintf(
                '  Primary: %s (P%s, %d items, opened %s)',
                $primary->order_number,
                $primary->total_amount,
                $primary->items->count(),
                $primary->created_at->format('M d g:i A'),
            ));

            foreach ($donors as $donor) {
                $this->line(sprintf(
                    '  Merging: %s (P%s, %d items, opened %s) -> into %s',
                    $donor->order_number,
                    $donor->total_amount,
                    $donor->items->count(),
                    $donor->created_at->format('M d g:i A'),
                    $primary->order_number,
                ));
            }

            if ($apply) {
                $this->mergeGroup($primary, $donors);
                $primary->refresh();
                $this->line(sprintf(
                    '  Done. %s now totals P%s across %d items.',
                    $primary->order_number,
                    $primary->total_amount,
                    $primary->items()->count(),
                ));
            }

            $this->newLine();
        }

        $this->info($groups->count().' table(s) with duplicate receipts '.($apply ? 'merged' : 'found').'.');

        if (! $apply) {
            $this->warn('Nothing was written. Re-run with --apply to perform the merge.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Order>  $donors
     */
    protected function mergeGroup(Order $primary, Collection $donors): void
    {
        DB::transaction(function () use ($primary, $donors) {
            $primary = Order::whereKey($primary->id)->lockForUpdate()->firstOrFail();

            foreach ($donors as $donor) {
                $donorLocked = Order::whereKey($donor->id)->lockForUpdate()->firstOrFail();
                $donorLocked->loadMissing('items');

                // Renumber the donor's batches to continue after whatever
                // the primary already has, so nothing collides once they
                // share one order.
                $batchOffset = (int) $primary->items()->max('batch_number');
                $distinctBatches = $donorLocked->items->pluck('batch_number')->unique()->sort()->values();
                $batchMap = $distinctBatches->mapWithKeys(fn ($old, $i) => [$old => $batchOffset + $i + 1]);

                foreach ($donorLocked->items as $item) {
                    $item->update([
                        'order_id' => $primary->id,
                        'batch_number' => $batchMap[$item->batch_number],
                    ]);
                }

                $donorLocked->update([
                    'status' => OrderStatus::Cancelled,
                    'voided_at' => now(),
                    'void_reason' => sprintf(
                        'Merged into order %s (duplicate receipt consolidation, %s) — items moved, not deleted.',
                        $primary->order_number,
                        now()->toDateTimeString(),
                    ),
                ]);
            }

            $primary->recalculateTotal();
        });
    }
}
