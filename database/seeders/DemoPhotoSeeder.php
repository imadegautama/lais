<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Support\Media;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Product photos for the demo shop, from database/seeders/demo-photos (sources
 * and licences in CREDITS.md). Only products without a photo get one, so it
 * can run again on a seeded server: `php artisan db:seed --force`.
 */
class DemoPhotoSeeder extends Seeder
{
    /** @var array<string, string> product name => file in demo-photos */
    public const PHOTOS = [
        'Nasi Goreng' => 'nasi-goreng.webp',
        'Mie Goreng' => 'mie-goreng.webp',
        'Es Teh' => 'es-teh.webp',
        'Air Mineral 600 ml' => 'air-mineral.webp',
        'Kerupuk' => 'kerupuk.webp',
    ];

    public function run(): void
    {
        $business = Business::query()->where('businesses.slug', 'warung-bu-rina')->first();

        if ($business === null) {
            return;
        }

        foreach (self::PHOTOS as $name => $file) {
            $source = __DIR__."/demo-photos/{$file}";
            $item = $business->items()->where('items.name', $name)->whereNull('items.photo_path')->first();

            if ($item === null || ! is_file($source)) {
                continue;
            }

            $path = 'produk/'.Str::random(40).'.webp';
            Media::disk()->put($path, (string) file_get_contents($source));
            // Quietly: a seeded photo is not an owner's edit for the activity log.
            $item->forceFill(['photo_path' => $path])->saveQuietly();
        }
    }
}
