<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Superadmin',
            'email' => 'dev@88hotspring.com',
            'password' => 'password',
            'role' => UserRole::Superadmin,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->call(SpaceStructureSeeder::class);
        $this->call(CookingStyleSeeder::class);
    }
}
