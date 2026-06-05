<?php

namespace Database\Seeders;

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
        // Global libraries are idempotent and required on every install.
        $this->call(GlobalLibrarySeeder::class);

        // Guard the unique email so re-running the seeder never duplicates the
        // dev account; the factory still supplies the hashed password.
        if (User::query()->where('email', 'test@example.com')->doesntExist()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }
}
