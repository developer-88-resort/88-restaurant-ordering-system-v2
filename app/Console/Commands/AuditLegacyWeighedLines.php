<?php

namespace App\Console\Commands;

use App\Enums\LineType;
use App\Enums\PricingType;
use App\Models\OrderItem;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Finds per-kilo lines that were billed before the weighing record existed
 * — and, in the worst cases, billed at ₱0.00.
 *
 * These are on real bills belonging to real customers, so this command
 * FLAGS and REPORTS. It never deletes and never voids: whether a ₱0.00
 * Bangus is written off, re-weighed, or chased up with the guest is a
 * manager's call, and one made with the order in front of them. Silently
 * cleaning them up would destroy the only evidence that anything went
 * wrong.
 */
class AuditLegacyWeighedLines extends Command
{
    protected $signature = 'weigh:audit-legacy-lines {--unflag : Clear the review flag instead of setting it}';

    protected $description = 'Flag per-kilo order lines with no weighing record or a zero price, for manager review';

    public function handle(): int
    {
        if ($this->option('unflag')) {
            $cleared = OrderItem::where('flagged_for_review', true)->update(['flagged_for_review' => false]);
            $this->info("Cleared the review flag on {$cleared} line(s).");

            return self::SUCCESS;
        }

        $suspect = $this->suspectLines();

        if ($suspect->isEmpty()) {
            $this->info('No legacy weighed lines need review.');

            return self::SUCCESS;
        }

        OrderItem::whereIn('id', $suspect->pluck('id'))->update(['flagged_for_review' => true]);

        $this->newLine();
        $this->warn($suspect->count().' line(s) flagged for manager review — none were changed or removed:');
        $this->newLine();

        $this->table(
            ['Order', 'Table', 'Item', 'Qty', 'Charged', 'Date', 'Why'],
            $suspect->map(fn (OrderItem $item) => [
                $item->order?->orderNumber() ?? '—',
                $item->order?->space?->name ?? __('Takeout'),
                $item->item_name,
                $item->quantity,
                '₱'.number_format((float) $item->subtotal, 2),
                $item->created_at?->format('Y-m-d') ?? '—',
                $this->reasonFor($item),
            ])->all(),
        );

        $this->newLine();
        $this->line('  Review each one in Order Management. Nothing was deleted or voided —');
        $this->line('  writing one off, re-weighing it, or billing the guest is your decision.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * A per-kilo line is suspect when it has no weighing behind it (so
     * nobody can say what was actually on the scale) or when it was billed
     * at nothing.
     *
     * @return Collection<int, OrderItem>
     */
    protected function suspectLines(): Collection
    {
        return OrderItem::query()
            ->with(['order.space', 'menuItem'])
            ->where(function ($query) {
                $query->whereHas('menuItem', fn ($q) => $q->where('pricing_type', PricingType::PerKilo))
                    ->orWhere('line_type', LineType::Weighed);
            })
            ->where(function ($query) {
                $query->whereDoesntHave('weighings')
                    ->orWhere('unit_price', '<=', 0);
            })
            ->orderBy('order_id')
            ->get();
    }

    protected function reasonFor(OrderItem $item): string
    {
        $reasons = [];

        if (bccomp((string) $item->unit_price, '0.00', 2) <= 0) {
            $reasons[] = 'billed at ₱0.00';
        }

        if ($item->weighings()->count() === 0) {
            $reasons[] = 'no weighing record';
        }

        return implode(', ', $reasons);
    }
}
