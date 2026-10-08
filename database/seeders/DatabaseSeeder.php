<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Идемпотентность для деплоя: если данные уже есть, повторный сид не нужен
        if (User::query()->exists()) {
            return;
        }

        $this->call([
            UserSeeder::class,
            ReferenceSeeder::class,
            ProjectSeeder::class,
            TaskTrackerSeeder::class,
        ]);
    }
}