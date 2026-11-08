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
        // Not a factory: Faker is not installed on the server. The account is
        // for judges, so it needs no email to confirm.
        User::query()->firstOrCreate(['email' => 'demo@lais.test'], [
            'name' => 'Bu Rina',
            'password' => config('services.demo.password'),
        ])->markEmailAsVerified();

        // Each step skips what is already there, so running db:seed again only fills gaps.
        $this->call([DemoBusinessSeeder::class, DemoHistorySeeder::class, DemoPhotoSeeder::class]);
    }
}
