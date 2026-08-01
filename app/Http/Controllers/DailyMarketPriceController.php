<?php

namespace App\Http\Controllers;

use App\Enums\PricingType;
use App\Http\Requests\StoreDailyMarketPricesRequest;
use App\Models\DailyMarketPrice;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The day's per-kilo market rates. Fish and meat rates move daily, so the
 * sellable rate is set here once each morning by a manager instead of being
 * typed at the scale — that separation is the whole point of the page: it
 * is the control against both typos and staff quietly inventing a price.
 *
 * A day with no row simply falls back to the item's standing
 * menu_items.price_per_kilo, so the page never has to be filled in for the
 * system to work; it only ever overrides.
 */
class DailyMarketPriceController extends Controller
{
    public function index(Request $request): Response
    {
        $date = $this->resolveDate($request);
        $yesterday = $date->copy()->subDay();

        $items = MenuItem::where('pricing_type', PricingType::PerKilo)
            ->with(['menuCategory'])
            ->orderBy('name')
            ->get();

        $todayRows = $this->pricesKeyedByItem($date);
        $yesterdayRows = $this->pricesKeyedByItem($yesterday);

        return Inertia::render('WeighPrices/Index', [
            'date' => $date->toDateString(),
            'today' => Carbon::today()->toDateString(),
            'isEditable' => $this->isEditable($date),
            'items' => $items->map(function (MenuItem $item) use ($todayRows, $yesterdayRows) {
                $row = $todayRows->get($item->id);
                $previous = $yesterdayRows->get($item->id);

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'category_name' => $item->menuCategory?->name,
                    // The standing rate, shown as the fallback whenever the
                    // day has no row of its own.
                    'default_price_per_kilo' => (float) $item->price_per_kilo,
                    'yesterday_price' => $previous
                        ? (float) $previous->price_per_kilo
                        : (float) $item->price_per_kilo,
                    'yesterday_was_set' => $previous !== null,
                    'price' => $row ? (float) $row->price_per_kilo : null,
                    'set_by' => $row?->setBy?->name,
                    'set_at' => $row?->created_at?->format('M j, Y g:i A'),
                ];
            })->values(),
        ]);
    }

    public function store(StoreDailyMarketPricesRequest $request): RedirectResponse
    {
        $date = $this->resolveDate($request);

        if (! $this->isEditable($date)) {
            return redirect()
                ->route('weigh.prices.index', ['date' => $date->toDateString()])
                ->with('error', __('Past prices are a record of what was actually charged and cannot be edited.'));
        }

        $rows = collect($request->validated('prices'))
            ->keyBy(fn (array $row) => (int) $row['menu_item_id']);

        // Only per-kilo items can carry a market rate; anything else in the
        // payload is ignored rather than trusted.
        $items = MenuItem::where('pricing_type', PricingType::PerKilo)
            ->whereIn('id', $rows->keys())
            ->get();

        $existing = $this->pricesKeyedByItem($date);
        $changed = 0;

        DB::transaction(function () use ($items, $rows, $existing, $date, $request, &$changed) {
            foreach ($items as $item) {
                $new = $rows->get($item->id)['price_per_kilo'] ?? null;

                if ($new === null || $new === '') {
                    continue;
                }

                $new = number_format((float) $new, 2, '.', '');
                $current = $existing->get($item->id);
                $old = $current
                    ? number_format((float) $current->price_per_kilo, 2, '.', '')
                    : number_format((float) $item->price_per_kilo, 2, '.', '');

                // Re-saving an unchanged row is a no-op, so the audit trail
                // stays a list of real rate changes instead of one entry per
                // item per press of Save.
                if ($current && bccomp($old, $new, 2) === 0) {
                    continue;
                }

                DailyMarketPrice::updateOrCreate(
                    ['menu_item_id' => $item->id, 'effective_date' => $date->toDateString()],
                    ['price_per_kilo' => $new, 'set_by_user_id' => $request->user()->id],
                );

                activity('audit')
                    ->causedBy($request->user())
                    ->performedOn($item)
                    ->event('daily_market_price_set')
                    ->withProperties([
                        'item' => $item->name,
                        'old_price_per_kilo' => $old,
                        'new_price_per_kilo' => $new,
                        'effective_date' => $date->toDateString(),
                        'was_fallback' => $current === null,
                    ])
                    ->log(sprintf(
                        'DAILY MARKET PRICE SET: %s — ₱%s/kg → ₱%s/kg for %s',
                        $item->name,
                        $old,
                        $new,
                        $date->toDateString(),
                    ));

                $changed++;
            }
        });

        return redirect()
            ->route('weigh.prices.index', ['date' => $date->toDateString()])
            ->with('status', $changed === 0
                ? __('No price changes to save.')
                : __(':count price(s) updated for :date.', ['count' => $changed, 'date' => $date->toDateString()]));
    }

    /**
     * @return \Illuminate\Support\Collection<int, DailyMarketPrice>
     */
    protected function pricesKeyedByItem(Carbon $date): \Illuminate\Support\Collection
    {
        return DailyMarketPrice::with('setBy')
            ->where('effective_date', $date->toDateString())
            ->get()
            ->keyBy('menu_item_id');
    }

    /**
     * Past days are a record of what was actually charged, so they are
     * read-only. The picker is capped at today, so "not past" means today.
     */
    protected function isEditable(Carbon $date): bool
    {
        return $date->isSameDay(Carbon::today());
    }

    /**
     * Always yields a real date no later than today: a malformed or future
     * `?date=` silently becomes today rather than 500-ing or letting someone
     * pre-date a rate.
     */
    protected function resolveDate(Request $request): Carbon
    {
        $raw = $request->string('date')->toString();

        if ($raw === '' || ! Carbon::hasFormat($raw, 'Y-m-d')) {
            return Carbon::today();
        }

        $date = Carbon::createFromFormat('Y-m-d', $raw)->startOfDay();

        return $date->isAfter(Carbon::today()) ? Carbon::today() : $date;
    }
}
