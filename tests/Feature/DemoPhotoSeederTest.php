<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Database\Seeders\DemoBusinessSeeder;
use Database\Seeders\DemoPhotoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoPhotoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_products_get_their_photos_once(): void
    {
        Storage::fake('public');
        User::factory()->create(['email' => 'demo@lais.test']);
        $this->seed(DemoBusinessSeeder::class);
        $business = Business::query()->where('businesses.slug', 'warung-bu-rina')->firstOrFail();
        $withFile = array_filter(DemoPhotoSeeder::PHOTOS, fn (string $file) => is_file(database_path("seeders/demo-photos/{$file}")));

        $this->seed(DemoPhotoSeeder::class);
        $paths = $business->items()->whereNotNull('items.photo_path')->pluck('items.photo_path', 'items.name');

        $this->assertEqualsCanonicalizing(array_keys($withFile), $paths->keys()->all());
        Storage::disk('public')->assertExists($paths->values()->all());

        // A second run leaves the photos as they are.
        $this->seed(DemoPhotoSeeder::class);
        $this->assertEquals($paths, $business->items()->whereNotNull('items.photo_path')->pluck('items.photo_path', 'items.name'));
        $this->assertCount($paths->count(), Storage::disk('public')->allFiles('produk'));
    }
}
