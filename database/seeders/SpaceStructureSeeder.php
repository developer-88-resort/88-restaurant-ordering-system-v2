<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\SpaceCategory;
use Illuminate\Database\Seeder;

class SpaceStructureSeeder extends Seeder
{
    /**
     * Seed the fixed Area taxonomy (Cottages, Dining Area, Rooms), each with
     * one matching category of the same name — mirrors the resort's actual
     * real-world setup. Individual Space units are created by the admin via
     * the UI, not seeded here.
     */
    public function run(): void
    {
        $areas = [
            ['name' => 'Cottages', 'slug' => 'cottages', 'sort_order' => 1],
            ['name' => 'Dining Area', 'slug' => 'dining-area', 'sort_order' => 2],
            ['name' => 'Rooms', 'slug' => 'rooms', 'sort_order' => 3],
        ];

        foreach ($areas as $area) {
            $created = Area::firstOrCreate(
                ['slug' => $area['slug']],
                ['name' => $area['name'], 'sort_order' => $area['sort_order'], 'is_active' => true]
            );

            SpaceCategory::firstOrCreate(
                ['area_id' => $created->id, 'slug' => $area['slug']],
                ['name' => $area['name'], 'sort_order' => 1, 'is_active' => true]
            );
        }
    }
}
