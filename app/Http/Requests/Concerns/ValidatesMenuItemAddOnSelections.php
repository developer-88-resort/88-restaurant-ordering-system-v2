<?php

namespace App\Http\Requests\Concerns;

use App\Models\MenuItem;
use Illuminate\Validation\Validator;

/**
 * Sibling to ValidatesMenuItemVariantSelections, shared by the same three
 * order-creation requests. Two concerns the plain per-field rules can't
 * express on their own:
 *  - An add-on id has to actually belong to the menu item it was submitted
 *    against — nothing stops a tampered request from pointing at another
 *    item's add-on otherwise.
 *  - The same add-on submitted twice for one line is almost certainly a
 *    client bug (quantity should have been bumped instead), not two
 *    legitimate lines the way two different variants would be.
 */
trait ValidatesMenuItemAddOnSelections
{
    protected function validateAddOnSelections(Validator $validator): void
    {
        foreach ((array) $this->input('items', []) as $index => $item) {
            $menuItemId = $item['menu_item_id'] ?? null;
            $addOnRows = (array) ($item['add_ons'] ?? []);

            if (! $menuItemId || $addOnRows === []) {
                continue;
            }

            $menuItem = MenuItem::with('addOns')->find($menuItemId);

            if (! $menuItem) {
                continue;
            }

            $seen = [];

            foreach ($addOnRows as $i => $row) {
                $addOnId = $row['id'] ?? null;

                if (! $addOnId || ! $menuItem->addOns->contains('id', (int) $addOnId)) {
                    $validator->errors()->add("items.{$index}.add_ons.{$i}.id", __('Invalid add-on selected for :name.', ['name' => $menuItem->name]));

                    continue;
                }

                if (in_array($addOnId, $seen, true)) {
                    $validator->errors()->add("items.{$index}.add_ons.{$i}.id", __('This add-on was selected more than once for :name.', ['name' => $menuItem->name]));
                }
                $seen[] = $addOnId;
            }
        }
    }
}
