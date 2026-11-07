<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing page (UI-PAGES A1). Its example shop is read from the real
 * shop, so the owner's photos show here too, and a shop that is missing or
 * has nothing to sell leaves no dead link behind.
 */
class LandingController extends Controller
{
    private const DEMO_SHOP = 'warung-bu-rina';

    public function __invoke(): Response
    {
        return Inertia::render('welcome', ['demoShop' => $this->demoShop()]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function demoShop(): ?array
    {
        $shop = Business::query()->where('businesses.slug', self::DEMO_SHOP)->first();

        if ($shop === null) {
            return null;
        }

        $items = ShopController::catalog($shop)
            ->filter(fn (array $item) => $item['orderable'])
            ->sortBy(fn (array $item) => $item['photoUrl'] === null)
            ->take(4)
            ->values();

        return $items->isEmpty() ? null : [...Arr::only(ShopController::shop($shop), ['name', 'slug', 'open']), 'items' => $items];
    }
}
