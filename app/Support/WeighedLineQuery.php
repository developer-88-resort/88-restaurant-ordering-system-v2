<?php

namespace App\Support;

use App\Enums\LineType;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The one join shape behind every "what does a weighed order_item currently
 * look like" query in the app: each weighed order_item joined to its own
 * latest (highest-revision) order_item_weighings row, the order it belongs
 * to, who weighed it, and which table.
 *
 * Extracted so Reports' Weighed Items summary (Superadmin\ReportController)
 * and the Weighed Lines detail table (Superadmin\WeighLogController) build
 * on the exact same foundation instead of two independently-maintained
 * copies of the same join — the kind of drift that previously showed up as
 * two report pages disagreeing about the same numbers.
 *
 * Callers still apply their own filters on top (date range, item/user/
 * channel/status, paid-only, voided) — this only fixes the join shape and
 * the base row set (every weighed order_item, current revision), not what
 * subset of rows a given report wants to see.
 */
class WeighedLineQuery
{
    public static function base(): Builder
    {
        $latest = DB::table('order_item_weighings')
            ->select('order_item_id', DB::raw('MAX(revision) as max_revision'))
            ->groupBy('order_item_id');

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoinSub($latest, 'latest', function ($join) {
                $join->on('latest.order_item_id', '=', 'order_items.id');
            })
            ->leftJoin('order_item_weighings as w', function ($join) {
                $join->on('w.order_item_id', '=', 'order_items.id')
                    ->on('w.revision', '=', 'latest.max_revision');
            })
            ->leftJoin('users as weighed_by', 'weighed_by.id', '=', 'w.weighed_by_user_id')
            ->leftJoin('spaces', 'spaces.id', '=', 'orders.space_id')
            ->where('order_items.line_type', LineType::Weighed->value);
    }
}
