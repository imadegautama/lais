<?php

namespace App\Http\Controllers;

use App\Enums\ItemKind;
use App\Enums\TransactionSource;
use App\Http\Requests\OrderRequest;
use App\Http\Resources\OrderResource;
use App\Jobs\SendWhatsApp;
use App\Models\Business;
use App\Models\Item;
use App\Models\Order;
use App\Services\Orders;
use App\Support\OrderMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public side (UI-PAGES A2–A4): the shop page, placing an order, and the
 * customer's nota. No account needed; the nota is reached by its token only.
 */
class ShopController extends Controller
{
    public function show(string $slug): Response
    {
        $business = Business::query()->where('businesses.slug', $slug)->firstOrFail();

        return Inertia::render('shop/show', [
            'shop' => self::shop($business),
            'items' => self::catalog($business),
        ]);
    }

    public function store(OrderRequest $request, string $slug, Orders $orders): RedirectResponse
    {
        $business = Business::query()->where('businesses.slug', $slug)->firstOrFail();
        // 5 orders per IP per 10 minutes (PRD §7); a form sent back with errors does not count.
        $key = 'shop-orders:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['lines' => 'Terlalu banyak pesanan dari jaringan ini. Coba lagi '.ceil(RateLimiter::availableIn($key) / 60).' menit lagi, atau hubungi penjual.']);
        }

        $order = $orders->place($business, $request->validated(), TransactionSource::Toko);
        RateLimiter::hit($key, 600);

        // The owner hears at once through the bot (PRD US-09); the bot never writes to the customer.
        $owner = $business->user;

        if ($owner->whatsappAddress() !== null) {
            SendWhatsApp::dispatch($owner->whatsappAddress(), OrderMessages::arrived($order->load('lines')), $owner->id);
        }

        return to_route('shop.order', $order->token);
    }

    public function order(string $token): Response
    {
        $order = Order::query()->where('orders.token', $token)->with(['business', 'lines'])->firstOrFail();

        return Inertia::render('shop/order', [
            'shop' => self::shop($order->business),
            'order' => OrderResource::make($order)->public(),
        ]);
    }

    /**
     * The shop's face: its name and link, whether it takes orders, the owner's
     * WA number for "Hubungi penjual", and the QRIS to pay to.
     *
     * @return array{name: string, slug: string, category: string|null, open: bool, whatsapp: string|null, qrisUrl: string|null}
     */
    public static function shop(Business $business): array
    {
        return [
            'name' => $business->name,
            'category' => $business->category?->label(),
            'slug' => $business->slug,
            'open' => $business->accepting_orders,
            'whatsapp' => $business->whatsapp === null ? null : Orders::phone($business->whatsapp),
            'qrisUrl' => $business->qrisUrl(),
        ];
    }

    /**
     * What the shop sells to customers, by name, with how many more each
     * may take once open orders are counted.
     *
     * @return Collection<int, array{id: int, name: string, unit: string, price: int|null, photoUrl: string|null, orderable: bool, left: int|null}>
     */
    public static function catalog(Business $business): Collection
    {
        $held = Orders::held($business);

        return $business->items()
            ->whereIn('items.kind', ItemKind::sold())
            ->where('items.show_in_store', true)
            ->whereNotNull('items.price')
            ->orderBy('items.name')
            ->get()
            ->map(function (Item $item) use ($held) {
                $left = Orders::left($item, $held[$item->id] ?? 0.0);

                return [...self::item($item), 'orderable' => Orders::orderable($item) && $left !== 0, 'left' => $left];
            })
            ->values()
            ->toBase();
    }

    /**
     * A product as the order form shows it; the price is shown, never sent back.
     * No limit here: the owner's own order form may take more than the stock.
     *
     * @return array{id: int, name: string, unit: string, price: int|null, photoUrl: string|null, orderable: bool, left: int|null}
     */
    public static function item(Item $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'unit' => $item->unit,
            'price' => $item->price,
            'photoUrl' => $item->photoUrl(),
            'orderable' => Orders::orderable($item),
            'left' => null,
        ];
    }
}
