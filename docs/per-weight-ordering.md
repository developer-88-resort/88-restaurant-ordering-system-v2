# Per-Weight Ordering

Fish, crab, shrimp and meat that 88 Hot Spring sells by the kilo instead of at a
fixed price per plate.

## Setting up a per-kilo item

Menu Management → **New Menu Item** → *Pricing & Prep* → set **Pricing Type** to
**Per Kilo (Weighed)**. The Price field is replaced by:

| Field | Meaning | Default |
| --- | --- | --- |
| **Price per Kilo (₱)** | Today's sellable rate. Required. | — |
| **Minimum Weight (g)** | Smallest portion you will sell. | 250 |
| **Weight Step (g)** | Increment the scale entry snaps to. | 10 |
| **Allow Tare** | Staff may deduct the container/ice weight before pricing. | off |
| **Counter Only** | Hidden from the customer QR menu; staff weigh and add it at the counter. | **on** |

Validation: the rate must be **₱10–₱10,000 per kilo** (a typo guard, not a
business limit — below ₱10/kg is nearly always a per-100g price typed by
mistake, above ₱10,000/kg a misplaced decimal), minimum weight **≥ 50 g**, and
weight step **≥ 1 g**.

### Cooking Styles replace Variants

A per-kilo item shows a **Cooking Styles** picker instead of the Variants
section. Variants (Solo/Medium/Large) make no sense here — the size is whatever
the scale says. Pick every style the kitchen will accept; the customer chooses
one when ordering. Styles come from the `cooking_styles` table, seeded with
Inihaw, Sinigang, Sweet and Sour, Salt and Pepper, Ginataan, Chilli Garlic,
Buttered and Paksiw. All seed at ₱0.00 because cooking is included in the
per-kilo price here; a surcharge is the exception an admin sets per style, and
it is charged **per piece** on the line.

Switching an item between Fixed and Per Kilo is safe in both directions: going
per-kilo drops its variants, going fixed detaches its cooking styles and resets
the weight fields to their defaults.

## Recommended: give weighed items their own category

**Nothing in Menu Categories needs to change for this feature to work** — a
per-kilo item can live in any existing category. But it is strongly recommended
to create a dedicated category for them, for example:

- **FRESH CATCH**
- **BY WEIGHT**

Why it is worth doing:

- **Customers read price differently.** A card showing `₱450.00 / kg` sitting
  between plated dishes priced `₱280.00` invites the reader to compare two
  numbers that mean different things. Grouping them sets the expectation once.
- **Most weighed items are Counter Only**, so they are hidden from the QR menu
  anyway. A separate category keeps the staff-facing list tidy instead of
  scattering hidden items through the customer-visible ones.
- **Market rates move daily.** When the day's rates change, one category is the
  natural worklist to run down.

Create it the usual way in Menu Categories, then pick it as the Category on each
weighed item.

## Where the money is computed

Every weighed line is priced by **one** class, `App\Support\WeighedLinePricer`:

```
net   = weight_grams − tare_grams
base  = round((net / 1000) × price_per_kilo, 2)
total = base + (cooking style surcharge × max(pieces, 1))
```

Rounding happens **in pesos, never on the kilo figure**. Any module that needs a
weighed line's charge must call this class — re-implementing the formula
elsewhere is a defect, because the two copies will disagree the first time a
rule changes.

`daily_market_prices` holds the per-day rate for an item;
`MenuItem::effectivePricePerKilo()` prefers today's row and falls back to the
item's standing `price_per_kilo`.
