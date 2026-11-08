<?php

namespace Tests\Feature;

use App\Enums\OnlinePaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\TransactionKind;
use App\Models\Business;
use App\Models\Item;
use App\Models\OnlinePayment;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\Doku;
use App\Services\Onboarding;
use App\Services\Orders;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DokuPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const PAGE = 'https://sandbox.doku.com/checkout-link-v2/abc123';

    private Business $business;

    private Item $water;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-18 14:00'));
        config(['services.doku' => ['client_id' => 'BRN-0001-TEST', 'secret_key' => 'SK-test-secret', 'base_url' => 'https://api-sandbox.doku.com']]);
        Http::preventStrayRequests();
        // A running Vite dev server would render pages through SSR, which is an HTTP call too.
        config(['inertia.ssr.enabled' => false]);
        $this->business = $this->openBooks();
        $this->business->update(['online_payment' => true]);
        $this->water = Item::query()->where('name', 'Air Mineral')->sole();
    }

    public function test_paying_online_opens_a_signed_doku_page_that_returns_to_the_nota(): void
    {
        $this->fakeDoku();

        $this->order()->assertRedirect(self::PAGE);

        $order = Order::query()->sole();
        $payment = OnlinePayment::query()->sole();
        $this->assertSame([OrderStatus::Menunggu, 12000, OnlinePaymentStatus::Menunggu], [$order->status, $payment->amount, $payment->status]);
        // DOKU writes the expiry in UTC+7.
        $this->assertTrue($payment->expires_at->eq(CarbonImmutable::parse('2026-11-18 15:00', 'Asia/Jakarta')));
        Http::assertSent(function (Request $request) use ($order, $payment) {
            $signed = Doku::signature('BRN-0001-TEST', $request->header('Request-Id')[0], $request->header('Request-Timestamp')[0], '/checkout/v1/payment', $request->body());

            return $request->header('Signature')[0] === $signed
                && $request['order']['amount'] === 12000
                && $request['order']['invoice_number'] === $payment->invoice_number
                && $request['order']['callback_url'] === route('shop.order', $order->token);
        });
        $this->assertMatchesRegularExpression('/^LAIS\d+[A-Z0-9]{8}$/', $payment->invoice_number);
        $this->assertLessThanOrEqual(30, strlen($payment->invoice_number));

        // "Bayar online" on the nota goes back to the page still open, without a second invoice.
        $this->post(route('shop.pay', $order->token))->assertRedirect(self::PAGE);
        Http::assertSentCount(1);
        $this->get(route('shop.order', $order->token))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('shop.onlinePayment', true)
            ->where('order.onlinePayment.status', 'menunggu')
            ->where('order.onlinePayment.url', self::PAGE));
    }

    public function test_when_doku_cannot_open_a_page_the_order_still_stands(): void
    {
        Http::fake(['api-sandbox.doku.com/*' => Http::response(['error_messages' => ['invalid']], 400)]);

        $this->order();

        $order = Order::query()->sole();
        $this->assertSame(0, OnlinePayment::query()->count());
        $this->assertSame(OrderStatus::Menunggu, $order->status);
    }

    public function test_a_signed_notification_books_the_payment_once(): void
    {
        $this->fakeDoku();
        $this->order();
        $payment = OnlinePayment::query()->sole();

        $this->notify($payment, 'SUCCESS', 12000)->assertNoContent();
        $this->notify($payment, 'SUCCESS', 12000)->assertNoContent();

        $order = Order::query()->sole();
        $deposits = Transaction::query()->where('transactions.kind', TransactionKind::UangMuka)->get();
        $this->assertSame(OrderStatus::Diproses, $order->status);
        $this->assertNotNull($order->contact_id);
        $this->assertCount(1, $deposits);
        $this->assertSame([12000, Doku::ACCOUNT_NAME], [$deposits[0]->total, $deposits[0]->moneyAccount->name]);
        $this->assertSame(12000, app(Orders::class)->paid($order));
        $this->assertSame([OnlinePaymentStatus::Lunas, 'QRIS'], [$payment->refresh()->status, $payment->channel]);
    }

    public function test_a_notification_doku_did_not_sign_or_that_does_not_match_changes_nothing(): void
    {
        $this->fakeDoku();
        $this->order();
        $payment = OnlinePayment::query()->sole();

        $this->notify($payment, 'SUCCESS', 12000, secret: 'SK-someone-else')->assertUnauthorized();
        $this->notify($payment, 'SUCCESS', 1000)->assertNoContent();

        $this->assertSame(OnlinePaymentStatus::Menunggu, $payment->refresh()->status);
        $this->assertSame(OrderStatus::Menunggu, Order::query()->sole()->status);
        $this->assertSame(0, Transaction::query()->where('transactions.kind', TransactionKind::UangMuka)->count());
    }

    public function test_money_for_a_refused_order_stays_out_of_the_books(): void
    {
        $this->fakeDoku();
        $this->order();
        $payment = OnlinePayment::query()->sole();
        app(Orders::class)->reject(Order::query()->sole(), 'Bahan habis');

        $this->notify($payment, 'SUCCESS', 12000)->assertNoContent();

        $this->assertSame(OnlinePaymentStatus::Lunas, $payment->refresh()->status);
        $this->assertSame(0, Transaction::query()->where('transactions.kind', TransactionKind::UangMuka)->count());
    }

    public function test_the_nota_asks_doku_when_the_notification_is_late(): void
    {
        $this->fakeDoku();
        $this->order();
        $order = Order::query()->sole();
        $payment = OnlinePayment::query()->sole();
        Http::fake(['api-sandbox.doku.com/orders/v1/status/*' => Http::response([
            'order' => ['invoice_number' => $payment->invoice_number, 'amount' => 12000],
            'transaction' => ['status' => 'SUCCESS'],
            'channel' => ['id' => 'VIRTUAL_ACCOUNT_BCA'],
        ])]);

        // DOKU asks for a minute before Check Status.
        $this->get(route('shop.order', $order->token));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/orders/v1/status/'));

        $this->travel(2)->minutes();
        $this->get(route('shop.order', $order->token))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('order.status', 'diproses')
            ->where('order.paymentStatus', 'lunas')
            ->where('order.onlinePayment.status', 'lunas')
            ->where('order.onlinePayment.url', null));
    }

    public function test_a_shop_that_does_not_offer_it_ignores_paying_online(): void
    {
        $this->business->update(['online_payment' => false]);

        $this->order()->assertRedirect(route('shop.order', Order::query()->sole()->token));
        $this->post(route('shop.pay', Order::query()->sole()->token))->assertNotFound();
        $this->get(route('shop.show', $this->business->slug))->assertInertia(fn (AssertableInertia $page) => $page->where('shop.onlinePayment', false));
    }

    private function fakeDoku(): void
    {
        Http::fake(['api-sandbox.doku.com/checkout/v1/payment' => Http::response([
            'message' => ['SUCCESS'],
            'response' => ['payment' => ['url' => self::PAGE, 'expired_date' => '20261118150000', 'token_id' => 'tok']],
        ])]);
    }

    private function order(): TestResponse
    {
        return $this->post(route('shop.store', $this->business->slug), [
            'customer_name' => 'Pak Budi',
            'customer_phone' => '081234567890',
            'fulfillment' => 'ambil',
            'lines' => [['item_id' => $this->water->id, 'qty' => 3]],
            'website' => '',
            'pay_online' => true,
        ]);
    }

    /** DOKU's HTTP notification, signed the way DOKU signs it. */
    private function notify(OnlinePayment $payment, string $status, int $amount, string $secret = 'SK-test-secret'): TestResponse
    {
        $body = ['order' => ['invoice_number' => $payment->invoice_number, 'amount' => $amount], 'transaction' => ['status' => $status], 'channel' => ['id' => 'QRIS']];
        $requestId = (string) Str::uuid();
        $timestamp = now('UTC')->format('Y-m-d\TH:i:s\Z');
        config(['services.doku.secret_key' => $secret]);
        $signature = Doku::signature('BRN-0001-TEST', $requestId, $timestamp, '/webhooks/doku', (string) json_encode($body));
        config(['services.doku.secret_key' => 'SK-test-secret']);

        return $this->withHeaders(['Client-Id' => 'BRN-0001-TEST', 'Request-Id' => $requestId, 'Request-Timestamp' => $timestamp, 'Signature' => $signature])
            ->postJson(route('webhooks.doku'), $body);
    }

    private function openBooks(): Business
    {
        $business = Business::factory()->create();
        [$cashBox, $rekening] = $business->moneyAccounts()->orderBy('id')->get()->all();

        app(Onboarding::class)->complete($business, [
            'name' => 'Warung Ani',
            'started_on' => '2026-10-01',
            'accounts' => [['id' => $cashBox->id, 'name' => 'Kas tunai', 'balance' => 500000], ['id' => $rekening->id, 'name' => 'BCA', 'balance' => 1000000]],
            'equipment' => [],
            'products' => [['name' => 'Air Mineral', 'kind' => 'produk', 'unit' => 'botol', 'price' => 4000, 'min_stock' => 20, 'opening_stock' => 100, 'opening_cost' => 2500]],
            'debts' => [],
        ]);

        return $business->refresh();
    }
}
