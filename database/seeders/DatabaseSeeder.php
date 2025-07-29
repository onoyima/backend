<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        \App\Models\ExeatCategory::firstOrCreate(['name' => 'medical'], ['description' => 'Medical Exeat', 'status' => 'active']);
        \App\Models\ExeatCategory::firstOrCreate(['name' => 'casual'], ['description' => 'Casual Exeat', 'status' => 'active']);
        \App\Models\ExeatCategory::firstOrCreate(['name' => 'emergency'], ['description' => 'Emergency Exeat', 'status' => 'active']);
        \App\Models\ExeatCategory::firstOrCreate(['name' => 'official'], ['description' => 'Official Exeat', 'status' => 'active']);
    }
}
