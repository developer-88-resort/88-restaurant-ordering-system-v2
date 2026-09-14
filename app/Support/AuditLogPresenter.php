<?php

namespace App\Support;

use Spatie\Activitylog\Models\Activity;

/**
 * One source of truth for turning a raw Activity row's `event` (and, where
 * relevant, `subject_type`) into what a human reads on the Audit Logs page —
 * both the table's Action badge and the filter dropdown's option list.
 * Extracted from resources/views/superadmin/audit-logs/index.blade.php's
 * inline closures so the two call sites can never drift apart.
 */
class AuditLogPresenter
{
    protected const AUTH_EVENTS = ['login', 'logout', 'failed_login'];

    protected const GENERIC_CRUD_EVENTS = ['created', 'updated', 'deleted'];

    /**
     * "Weigh-in recorded", "Invoice Snapshot · Created", "Record Created",
     * ... — accepts anything with ->event and ->subject_type (a real
     * Activity row, or a lightweight stand-in built for a filter option)
     * so the same logic serves both a fully-qualified table row and a bare
     * event/subject pair.
     */
    public static function label(object $log): string
    {
        if (in_array($log->event, self::AUTH_EVENTS, true)) {
            return self::humanize($log->event);
        }

        $weighEventLabels = WeighAudit::events() + [
            'daily_market_price_set' => __('Daily market price set'),
        ];

        if (isset($weighEventLabels[$log->event])) {
            return $weighEventLabels[$log->event];
        }

        $subject = self::subjectLabel($log->subject_type ?? null);

        if (! $subject) {
            return match ($log->event) {
                'created' => __('Record Created'),
                'updated' => __('Updated'),
                'deleted' => __('Deleted'),
                default => self::humanize($log->event ?? ''),
            };
        }

        return match ($log->event) {
            'created' => __(':subject · Created', ['subject' => $subject]),
            'updated' => __(':subject · Updated', ['subject' => $subject]),
            'deleted' => __(':subject · Deleted', ['subject' => $subject]),
            default => $subject.' · '.self::humanize($log->event ?? ''),
        };
    }

    /** "advance_order_added" -> "Advance Order Added" — one humanizer, every label goes through it. */
    protected static function humanize(string $event): string
    {
        return __(ucwords(str_replace('_', ' ', $event)));
    }

    /** One place to decide a badge's colour — every event type gets a sensible one. */
    public static function badgeClasses(?string $event): string
    {
        return match (true) {
            $event === 'created' => 'bg-green-100 text-green-800',
            $event === 'updated' => 'bg-blue-100 text-blue-800',
            $event === 'deleted' => 'bg-red-100 text-red-800',
            $event === 'login' => 'bg-teal-100 text-teal-800',
            $event === 'logout' => 'bg-gray-100 text-gray-600',
            $event === 'failed_login' => 'bg-amber-100 text-amber-800',
            str_contains((string) $event, 'weigh') || str_contains((string) $event, 'variance') => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-700',
        };
    }

    /**
     * The short, human name for a model — deliberately NOT just a
     * class_basename-derived guess, so "OrderInvoiceSnapshot" reads as
     * "Invoice Snapshot" and "OrderItemAdjustment" as "Item Adjustment"
     * rather than surfacing the raw class name.
     */
    protected static function subjectLabel(?string $subjectType): ?string
    {
        return match ($subjectType ? class_basename($subjectType) : null) {
            'MenuCategory' => __('Menu Category'),
            'MenuItem' => __('Menu Item'),
            'SpaceCategory' => __('Space Category'),
            'Setting' => __('Settings'),
            'OrderItem' => __('Order'),
            'OrderInvoiceSnapshot' => __('Invoice Snapshot'),
            'OrderItemAdjustment' => __('Item Adjustment'),
            'OrderPayment' => __('Payment Entry'),
            'DiscountRule' => __('Discount Rule'),
            'Quotation' => __('Quotation'),
            'Promotion' => __('Promotion'),
            'Space' => __('Space'),
            'Order' => __('Order'),
            'Area' => __('Area'),
            'User' => __('User'),
            null => null,
            default => class_basename($subjectType),
        };
    }

    /**
     * Every distinct filterable action, human labeled — powers the Action
     * multi-select. Custom-named events (weigh_in_recorded, ...) are
     * already unique on their own. Generic CRUD events (created/updated/
     * deleted) are NOT unique — "created" alone matches every audited
     * model — so each is split into one option PER subject_type actually
     * seen in the log, keyed "event:FqcnOfSubject", so a filter option
     * matches exactly the same rows its badge represents in the table
     * (e.g. "Invoice Snapshot · Created" only pulls OrderInvoiceSnapshot
     * rows, not every "created" event across the whole app).
     *
     * @return array<string, string> value => label
     */
    public static function filterOptions(): array
    {
        $customEvents = Activity::query()
            ->whereNotNull('event')
            ->whereNotIn('event', self::GENERIC_CRUD_EVENTS)
            ->distinct()
            ->orderBy('event')
            ->pluck('event');

        $options = $customEvents->mapWithKeys(fn ($event) => [
            $event => self::label((object) ['event' => $event, 'subject_type' => null]),
        ])->all();

        $crudCombinations = Activity::query()
            ->whereIn('event', self::GENERIC_CRUD_EVENTS)
            ->whereNotNull('subject_type')
            ->select('event', 'subject_type')
            ->distinct()
            ->get();

        foreach ($crudCombinations as $row) {
            $key = $row->event.':'.$row->subject_type;
            $options[$key] = self::label((object) ['event' => $row->event, 'subject_type' => $row->subject_type]);
        }

        asort($options);

        return $options;
    }
}
