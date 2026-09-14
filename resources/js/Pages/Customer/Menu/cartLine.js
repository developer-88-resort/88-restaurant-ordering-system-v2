/**
 * Shared cart-line pricing/identity helpers — extracted here because
 * price*qty math was already duplicated across AddConfirmModal.jsx,
 * useCart.js, and OrderConfirmModal.jsx before add-ons existed; a third
 * component (add-on totals) is one duplication too many to keep copying by
 * hand across three files.
 */

/**
 * A line's full price: the base item (unit price × its own quantity) plus
 * every selected add-on (each priced at ITS OWN quantity, independent of
 * the parent's) — NOT (unitPrice + sum(addOnPrices)) × qty. Two Bilao Sets
 * with one Crispy Pata add-on is base×2 + addOn×1, not base×2 + addOn×2.
 */
export function cartLineTotal(line) {
    const base = line.price * line.qty;
    const addOnsTotal = (line.addOns ?? []).reduce((sum, addOn) => sum + addOn.price * addOn.qty, 0);

    return base + addOnsTotal;
}

/**
 * A stable string identity for a line's add-on selection, so two lines for
 * the same item+variant+notes but different add-on choices are treated as
 * genuinely different lines (same reasoning already applied to `notes`)
 * rather than silently merged.
 */
export function addOnsKey(addOns) {
    return (addOns ?? [])
        .map((addOn) => `${addOn.id}:${addOn.qty}`)
        .sort()
        .join(',');
}
