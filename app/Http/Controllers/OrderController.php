<?php

namespace App\Http\Controllers;

use App\Enums\ItemKind;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TransactionSource;
use App\Http\Requests\OrderRequest;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\ShopSettingsRequest;
use App\Http\Resources\MoneyAccountResource;
use App\Http\Resources\OrderResource;
use App\Models\Business;
use App\Models\Order;
use App\Services\Doku;
use App\Services\Ledger;
use App\Services\Orders;
use App\Support\Media;
use App\Support\OrderMessages;
use App\Support\Proof;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pesanan for the owner (UI-PAGES C9, C10): the list by status, one order
 * and its next step, payments, and the shop's switches.
 */
class OrderController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $business = $request->user()->currentBusiness();
        $counts = $business->orders()->toBase()->groupBy('orders.status')->selectRaw('orders.status, count(*) as total')->pluck('total', 'status');
        // Tabs link with ?status=, the table keeps it as ?status[]= when searching or paging.
        $requested = $request->query('status');
        $status = OrderStatus::tryFrom((string) (is_array($requested) ? ($requested[0] ?? '') : $requested)) ?? OrderStatus::Menunggu;

        return Inertia::render('orders/index', [
            'shop' => [...ShopController::shop($business), 'url' => route('shop.show', $business->slug), 'onlinePaymentReady' => Doku::ready(), 'qrisMoneyAccountId' => $business->qris_money_account_id],
            'status' => $status,
            'counts' => collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $option) => [$option->value => (int) ($counts[$option->value] ?? 0)]),
            'orders' => TableQuery::paginate(
                $request,
                $business->orders()->getQuery()->where('orders.status', $status)->with(['lines', 'sale']),
                // shortcut: paid() reads Debts once per row (20 per page); batch it if pages grow.
                map: fn (Order $order) => OrderResource::make($order)->resolve(),
                sorts: ['masuk' => 'created_at', 'total' => 'total', 'waktu' => 'wanted_at'],
                // A queue is served first come, first served; history reads newest first.
                defaultSort: in_array($status, [OrderStatus::Selesai, OrderStatus::Ditolak], true) ? '-masuk' : 'masuk',
                // Applied above; listed so the tab stays in the URL when searching or paging.
                filters: ['status' => [fn () => null, OrderStatus::values()]],
                search: ['code', 'customer_name', 'customer_phone'],
            ),
            'moneyAccounts' => MoneyAccountResource::options($business, $ledger),
            // For an order the owner enters: every product with a price, shown in the shop or not.
            'items' => $business->items()
                ->whereIn('items.kind', ItemKind::sold())
                ->whereNotNull('items.price')
                ->orderBy('items.name')
                ->get()
                ->map(ShopController::item(...))
                ->values(),
        ]);
    }

    /**
     * An order taken by phone or chat, accepted at once.
     */
    public function store(OrderRequest $request, Orders $orders): RedirectResponse
    {
        $order = $orders->place($request->user()->currentBusiness(), $request->validated(), TransactionSource::Web);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Pesanan {$order->code} dicatat."]);

        return to_route('orders.show', $order->code);
    }

    public function show(Request $request, string $code, Ledger $ledger, Orders $orders): Response
    {
        $business = $request->user()->currentBusiness();
        $order = $this->find($business, $code);

        return Inertia::render('orders/show', [
            'shop' => [...ShopController::shop($business), 'url' => route('shop.show', $business->slug), 'onlinePaymentReady' => Doku::ready(), 'qrisMoneyAccountId' => $business->qris_money_account_id],
            'order' => OrderResource::make($order->load(['lines']))->resolve(),
            'notaUrl' => route('shop.order', $order->token),
            // The same words the bot links to (OrderMessages), so web and WhatsApp never differ.
            'statusMessage' => OrderMessages::status($order, $orders->paid($order)),
            'moneyAccounts' => MoneyAccountResource::options($business, $ledger),
        ]);
    }

    public function accept(Request $request, string $code, Orders $orders): RedirectResponse
    {
        $order = $orders->accept($this->find($request->user()->currentBusiness(), $code));

        return $this->done("Pesanan {$order->code} diterima.");
    }

    public function reject(Request $request, string $code, Orders $orders): RedirectResponse
    {
        $order = $orders->reject($this->find($request->user()->currentBusiness(), $code), $this->reason($request));

        return $this->done("Pesanan {$order->code} ditolak.");
    }

    public function ready(Request $request, string $code, Orders $orders): RedirectResponse
    {
        $order = $orders->markReady($this->find($request->user()->currentBusiness(), $code));

        return $this->done("Pesanan {$order->code} siap.");
    }

    public function complete(Request $request, string $code, Orders $orders): RedirectResponse
    {
        $order = $orders->complete($this->find($request->user()->currentBusiness(), $code));

        return $this->done("Pesanan {$order->code} selesai dan tercatat sebagai penjualan.");
    }

    public function cancel(Request $request, string $code, Orders $orders): RedirectResponse
    {
        $order = $orders->cancel($this->find($request->user()->currentBusiness(), $code), $this->reason($request));

        return $this->done("Pesanan {$order->code} dibatalkan.");
    }

    public function pay(PaymentRequest $request, string $code, Orders $orders): RedirectResponse
    {
        $data = $request->validated();
        $order = $this->find($request->user()->currentBusiness(), $code);
        $payment = $orders->recordPayment($order, (int) $data['amount'], PaymentMethod::from($data['method']), $data['money_account_id'] ?? null);

        if ($request->hasFile('proof')) {
            Proof::attach($payment, $request->file('proof'));
        }

        return $this->done("Pembayaran pesanan {$order->code} dicatat.");
    }

    /**
     * Open or close the shop, the owner's WA number, the QRIS image, and bayar online.
     */
    public function updateShop(ShopSettingsRequest $request): RedirectResponse
    {
        $business = $request->user()->currentBusiness();
        $data = $request->safe()->only(['accepting_orders', 'whatsapp', 'online_payment', 'qris_money_account_id']);

        if ($request->hasFile('qris') || $request->boolean('remove_qris')) {
            if ($business->qris_path !== null) {
                Media::disk()->delete($business->qris_path);
            }

            $data['qris_path'] = $request->hasFile('qris') ? $request->file('qris')->store('qris', Media::diskName()) : null;
        }

        $business->update($data);

        return $this->done('Pengaturan toko disimpan.');
    }

    /**
     * Codes repeat across businesses, so an order is looked up in the owner's own.
     */
    private function find(Business $business, string $code): Order
    {
        return $business->orders()->where('orders.code', $code)->with(['lines', 'business'])->firstOrFail();
    }

    private function reason(Request $request): ?string
    {
        return $request->validate(['reason' => ['nullable', 'string', 'max:200']])['reason'] ?? null;
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
