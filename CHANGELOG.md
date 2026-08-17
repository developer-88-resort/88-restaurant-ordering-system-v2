# Changelog

## 2026-08-08 — Advance Order append fixes

**Fixed: `POST /quotations` 500 on multi-character idempotency keys.**
`append_idempotency_keys.key` was a bare-UUID `CHAR(36)` column, but
`OrderAppender::appendBatch()` packed each line's key as `"{uuid}:{index}"`
(38+ chars) — overflowing it. Split the column into `request_uuid`
(`CHAR(36)`) + `line_index` (unsigned int) with a composite unique index,
backfilling existing rows by splitting the old value on its last `:`. Added
a length guard in `AppendIdempotencyKey::remember()` so an oversized value
fails loudly instead of silently truncating and colliding two requests'
keys under a laxer SQL mode.

**Fixed: advance orders appended to a newly-created order instead of the
receipt staff explicitly chose.** `QuotationController::store()` forced a
standalone order whenever `scheduled_for` was in the future — even a few
minutes — silently overriding an explicit "add to this receipt" pick from
the create screen's destination panel. Staff's explicit choice is now
honored unconditionally; only choosing "new receipt" ever creates a
standalone order. The destination is still re-validated server-side at
commit time (same table, current active session, still open) before any
line is written.

**Added:** `order_items.scheduled_for` — a receipt can now hold a mix of
already-cooking and advance-scheduled lines; the reserved time lives on
each line rather than only on the order.

**Added:** a dedicated `advance_order_added` audit log entry per submit
(who, quotation, receipt joined, line count, total amount), alongside the
existing per-line `order_line_appended` entries.

**Added:** `QuotationController::store()` now catches unexpected failures
and returns a readable flashed error instead of a raw exception response —
the create screen's in-progress form (table, items, schedule) is never
lost on failure.
