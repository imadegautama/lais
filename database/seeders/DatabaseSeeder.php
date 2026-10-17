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
        // Temporary login for the frontend phase. Phone + OTP login replaces
        // email in backend milestone M0 (PRD US-01).
        User::factory()->create([
            'name' => 'Bu Rina',
            'email' => 'demo@laris.test',
        ]);
    }
}
