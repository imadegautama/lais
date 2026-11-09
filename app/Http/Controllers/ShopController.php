<?php

namespace App\Http\Controllers;

use App\Enums\ItemKind;
use App\Enums\OnlinePaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\TransactionSource;
use App\Http\Requests\OrderRequest;
use App\Http\Resources\OrderResource;
use App\Jobs\SendWhatsApp;
use App\Models\Business;
use App\Models\Item;
use App\Models\Order;
use App\Services\Doku;
use App\Services\Orders;
use App\Services\Push;
use App\Support\OrderMessages;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

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

    public function store(OrderRequest $request, string $slug, Orders $orders, Doku $doku, Push $push): HttpResponse
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
            SendWhatsApp::dispatch($owner->whatsappAddress(), OrderMessages::arrived($order->load('lines')), $owner->id, OrderMessages::decide($order));
        }

        // And on the phones and browsers where the owner turned notifications on.
        $push->orderArrived($order);

        return $request->boolean('pay_online') && Doku::offeredBy($business) ? $this->payPage($order, $doku) : to_route('shop.order', $order->token);
    }

    /**
     * "Bayar online" on the nota: a DOKU page for what is still unpaid, while
     * the order is open.
     */
    public function pay(string $token, Orders $orders, Doku $doku): HttpResponse
    {
        $order = Order::query()->where('orders.token', $token)->with('business')->firstOrFail();

        abort_unless(Doku::offeredBy($order->business) && in_array($order->status, OrderStatus::holdsStock(), true) && $orders->paid($order) < $order->total, 404);

        return $this->payPage($order, $doku);
    }

    public function order(string $token, Doku $doku): Response
    {
        $order = Order::query()->where('orders.token', $token)->with(['business', 'lines'])->firstOrFail();
        $waiting = $order->onlinePayments()->latest('id')->first();

        // DOKU asks for a minute before Check Status; the notification usually comes first.
        // Asked until DOKU gives a final word, so a page paid just before it expired is not missed.
        if ($waiting?->status === OnlinePaymentStatus::Menunggu && $waiting->created_at->lt(now()->subMinute())) {
            $doku->refresh($waiting);
            $order->refresh();
        }

        return Inertia::render('shop/order', [
            'shop' => self::shop($order->business),
            'order' => OrderResource::make($order)->public(),
        ]);
    }

    /**
     * Off to DOKU's payment page. When DOKU cannot open one, the order still
     * stands and the nota says to try again or pay the seller.
     */
    private function payPage(Order $order, Doku $doku): HttpResponse
    {
        try {
            return Inertia::location($doku->checkout($order)->url);
        } catch (Throwable $exception) {
            report($exception);
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Pembayaran online belum bisa dibuka. Coba lagi dari nota ini, atau bayar ke penjual.']);

            return to_route('shop.order', $order->token);
        }
    }

    /**
     * The shop's face: its name and link, whether it takes orders, the owner's
     * WA number for "Hubungi penjual", the QRIS to pay to, and whether it
     * takes payment online.
     *
     * @return array{name: string, slug: string, category: string|null, open: bool, whatsapp: string|null, qrisUrl: string|null, onlinePayment: bool}
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
            'onlinePayment' => Doku::offeredBy($business),
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
