<?php

namespace Database\Seeders;

use App\Models\CookingStyle;
use Illuminate\Database\Seeder;

/**
 * Starter cooking styles for weighed fish/meat orders. Idempotent and safe
 * to run against the live database.
 *
 * Every style is created at ₱0.00: at 88 Hot Spring cooking is included in
 * the per-kilo price, and a surcharge is the exception an admin sets later
 * per style. Re-running refreshes only the translation and ordering — an
 * admin's `surcharge` and `is_active` edits are never overwritten, which is
 * why those two are set on first insert only.
 */
class CookingStyleSeeder extends Seeder
{
    public function run(): void
    {
        $styles = [
            ['name' => 'Inihaw', 'name_ko' => '구이'],
            ['name' => 'Sinigang', 'name_ko' => '시니강'],
            ['name' => 'Sweet and Sour', 'name_ko' => '탕수'],
            ['name' => 'Salt and Pepper', 'name_ko' => '소금 후추'],
            ['name' => 'Ginataan', 'name_ko' => '코코넛 밀크'],
            ['name' => 'Chilli Garlic', 'name_ko' => '칠리 갈릭'],
            ['name' => 'Buttered', 'name_ko' => '버터'],
            ['name' => 'Paksiw', 'name_ko' => '팍시우'],
        ];

        foreach ($styles as $index => $style) {
            $existing = CookingStyle::where('name', $style['name'])->first();

            if ($existing) {
                $existing->update([
                    'name_ko' => $style['name_ko'],
                    'sort_order' => ($index + 1) * 10,
                ]);

                continue;
            }

            CookingStyle::create([
                'name' => $style['name'],
                'name_ko' => $style['name_ko'],
                'surcharge' => 0,
                'sort_order' => ($index + 1) * 10,
                'is_active' => true,
            ]);
        }
    }
}
