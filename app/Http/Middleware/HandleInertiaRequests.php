<?php

namespace App\Http\Middleware;

use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            // Shown on the Pesanan menu; read on every page, so one indexed count.
            'pendingOrders' => fn () => $request->user()?->business?->orders()->where('orders.status', OrderStatus::Menunggu)->count() ?? 0,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Dates on screen use the server's zone (APP_TIMEZONE), not the device's.
            'timeZone' => [
                'name' => config('app.timezone'),
                'label' => CarbonImmutable::now(config('app.timezone'))->format('T'),
            ],
        ];
    }
}
