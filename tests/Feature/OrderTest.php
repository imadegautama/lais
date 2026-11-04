<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\StockReason;
use App\Enums\TransactionKind;
use App\Enums\TransactionSource;
use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Item;
use App\Models\Order;
use App\Services\Inventory;
use App\Services\Ledger;
use App\Services\Onboarding;
use App\Services\Orders;
use App\Services\Reports;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Item $water;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-18 14:00'));
        $this->business = $this->openBooks();
        $this->water = Item::query()->where('name', 'Air Mineral')->sole();
    }

    public function test_a_guest_orders_at_catalog_prices_and_lands_on_the_nota(): void
    {
        $response = $this->post(route('shop.store', $this->business->slug), $this->form([
            'customer_phone' => '0812-3456-7890',
            // A price sent by the browser is ignored: the catalog decides.
            'lines' => [['item_id' => $this->water->id, 'qty' => 3, 'price' => 1]],
        ]));

        $order = Order::query()->sole();
        $response->assertRedirect(route('shop.order', $order->token));
        $this->assertSame(['A001', 32, 12000, OrderStatus::Menunggu, TransactionSource::Toko, '6281234567890', null], [
            $order->code, strlen($order->token), $order->total, $order->status, $order->source, $order->customer_phone, $order->contact_id,
        ]);
        $this->assertSame(['Air Mineral', 'botol', 3, 4000], [$order->lines[0]->name, $order->lines[0]->unit, $order->lines[0]->qty, $order->lines[0]->price]);

        $this->get(route('shop.order', $order->token))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/order')
            ->where('shop.name', 'Warung Ani')
            ->where('order.code', 'A001')
            ->where('order.paymentStatus', 'belum')
            ->missing('order.customerPhone')
            ->missing('order.payments'));
        $this->get(route('shop.order', str_repeat('x', 32)))->assertNotFound();
    }

    public function test_the_shop_lists_shown_products_and_marks_sold_out_ones(): void
    {
        Item::factory()->for($this->business)->create(['name' => 'Es Teh']);
        Item::factory()->for($this->business)->create(['name' => 'Rahasia', 'show_in_store' => false]);

        $this->get(route('shop.show', $this->business->slug))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/show')
            ->where('shop.open', true)
            ->has('items', 2)
            ->where('items.0.name', 'Air Mineral')
            ->where('items.0.orderable', true)
            ->where('items.1.name', 'Es Teh')
            ->where('items.1.orderable', false));
        $this->get(route('shop.show', 'tidak-ada'))->assertNotFound();
    }

    public function test_orders_the_shop_cannot_take_are_refused(): void
    {
        $hidden = Item::factory()->for($this->business)->create(['show_in_store' => false]);
        $soldOut = Item::factory()->for($this->business)->create();
        $offRecipe = Item::factory()->recipe()->for($this->business)->create(['available' => false]);
        $foreign = Item::factory()->create();
        $refused = fn (array $data, string $field) => $this->post(route('shop.store', $this->business->slug), $this->form($data))->assertSessionHasErrors($field);

        foreach ([$hidden, $soldOut, $offRecipe, $foreign] as $item) {
            $refused(['lines' => [['item_id' => $item->id, 'qty' => 1]]], 'lines');
        }
        $refused(['website' => 'https://spam.example'], 'website');
        $refused(['customer_phone' => '12345'], 'customer_phone');
        $refused(['fulfillment' => 'antar', 'address' => ''], 'address');
        $refused(['lines' => [['item_id' => $this->water->id, 'qty' => 1000]]], 'lines.0.qty');

        $this->business->update(['accepting_orders' => false]);
        $refused([], 'lines');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_network_places_at_most_five_orders_in_ten_minutes(): void
    {
        $send = fn () => $this->post(route('shop.store', $this->business->slug), $this->form());

        // A form sent back with errors does not count.
        $this->post(route('shop.store', $this->business->slug), $this->form(['customer_phone' => '1']))->assertSessionHasErrors('customer_phone');

        foreach (range(1, 5) as $ignored) {
            $send()->assertSessionHasNoErrors();
        }
        $send()->assertSessionHasErrors('lines');
        $this->assertSame(5, Order::query()->count());

        $this->travel(11)->minutes();
        $send()->assertSessionHasNoErrors();
    }

    public function test_an_order_runs_from_accept_to_paid_and_the_books_stay_balanced(): void
    {
        // Known by WA number under another name: the order joins that tab.
        $known = $this->business->contacts()->create(['type' => 'pelanggan', 'name' => 'Bu Sari', 'phone' => '6281234567890']);
        $this->post(route('shop.store', $this->business->slug), $this->form(['lines' => [['item_id' => $this->water->id, 'qty' => 5]]]));
        $order = Order::query()->sole();
        $this->actingAs($this->business->user);
        $reports = app(Reports::class);

        $this->post(route('orders.accept', $order->code))->assertSessionHasNoErrors();
        $this->assertSame([OrderStatus::Diproses, $known->id], [$order->refresh()->status, $order->contact_id]);

        // Uang muka: cash in, owed to the customer until the order is done.
        $this->post(route('orders.pay', $order->code), ['amount' => 8000, 'method' => 'tunai'])->assertSessionHasNoErrors();
        $this->assertSame(8000, app(Ledger::class)->balance($this->business, Account::CUSTOMER_DEPOSITS));
        $this->assertSame(100.0, (float) $this->water->refresh()->stock);

        $this->post(route('orders.ready', $order->code))->assertSessionHasNoErrors();
        $this->post(route('orders.complete', $order->code))->assertSessionHasNoErrors();

        $order->refresh();
        $sale = $order->sale;
        $this->assertSame(OrderStatus::Selesai, $order->status);
        $this->assertSame([PaymentMethod::Kredit, 20000, 8000, TransactionSource::Toko, $order->id, $known->id], [$sale->method, $sale->total, $sale->paid_amount, $sale->source, $sale->order_id, $sale->contact_id]);
        $this->assertSame(95.0, (float) $this->water->refresh()->stock);
        $this->assertSame(0, app(Ledger::class)->balance($this->business, Account::CUSTOMER_DEPOSITS));
        $this->assertSame([$known->id => 12000], app(Ledger::class)->contactBalances($this->business));

        $this->post(route('orders.pay', $order->code), ['amount' => 12001, 'method' => 'tunai'])->assertSessionHasErrors('amount');
        $this->post(route('orders.pay', $order->code), ['amount' => 12000, 'method' => 'tunai'])->assertSessionHasNoErrors();
        $this->assertSame(TransactionKind::TerimaPiutang, $order->transactions()->latest('id')->first()->kind);
        $this->assertSame(20000, app(Orders::class)->paid($order));
        $this->post(route('orders.pay', $order->code), ['amount' => 1, 'method' => 'tunai'])->assertSessionHasErrors('amount');

        $this->get(route('orders.show', $order->code))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('orders/show')
            ->where('order.paymentStatus', 'lunas')
            ->where('order.saleId', $sale->id)
            ->has('order.payments', 2));

        $from = CarbonImmutable::parse('2026-11-01');
        $this->assertTrue($reports->balanceSheet($this->business, CarbonImmutable::parse('2026-11-18'))['balanced']);
        $this->assertSame(0, $reports->cashReconciliation($this->business, $from)['difference']);
        // 20.000 sold at a cost of 5 × 2.500.
        $this->assertSame(7500, $reports->profitAndLoss($this->business, $from)['netProfit']);
    }

    public function test_cancelling_an_accepted_order_returns_its_uang_muka(): void
    {
        $this->actingAs($this->business->user);
        $this->post(route('orders.store'), $this->form())->assertRedirect(route('orders.show', 'A001'));
        $order = Order::query()->sole();
        $this->post(route('orders.pay', 'A001'), ['amount' => 3000, 'method' => 'tunai'])->assertSessionHasNoErrors();

        $this->post(route('orders.reject', 'A001'))->assertSessionHasErrors('order');
        $this->post(route('orders.cancel', 'A001'), ['reason' => 'Bahan habis'])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame([OrderStatus::Ditolak, 'Bahan habis'], [$order->status, $order->reject_reason]);
        $this->assertSame([TransactionStatus::Void], $order->transactions()->pluck('status')->all());
        $this->assertSame(0, app(Ledger::class)->balance($this->business, Account::CUSTOMER_DEPOSITS));
        $this->assertSame(0, app(Orders::class)->paid($order));

        foreach (['orders.accept', 'orders.ready', 'orders.complete', 'orders.cancel'] as $step) {
            $this->post(route($step, 'A001'))->assertSessionHasErrors('order');
        }
        $this->post(route('orders.pay', 'A001'), ['amount' => 1000, 'method' => 'tunai'])->assertSessionHasErrors('order');
    }

    public function test_an_order_taken_by_phone_is_accepted_at_once_and_may_use_hidden_products(): void
    {
        $hidden = Item::factory()->for($this->business)->create(['show_in_store' => false, 'price' => 7000]);
        app(Inventory::class)->receive($hidden, 10, 30000, StockReason::StokAwal, Account::OWNER_CAPITAL);

        $this->actingAs($this->business->user)->post(route('orders.store'), $this->form([
            'lines' => [['item_id' => $hidden->id, 'qty' => 2]],
        ]));

        $order = Order::query()->sole();
        $this->assertSame([OrderStatus::Diproses, TransactionSource::Web, 14000], [$order->status, $order->source, $order->total]);
        $this->assertNotNull($order->accepted_at);
        $this->assertSame('Pak Budi', Contact::query()->findOrFail($order->contact_id)->name);
        // Taken by the owner, so nothing waits on the menu.
        $this->get(route('orders.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('pendingOrders', 0));
    }

    public function test_waiting_orders_show_on_the_menu_and_the_dashboard(): void
    {
        $this->post(route('shop.store', $this->business->slug), $this->form());
        $this->actingAs($this->business->user);

        $this->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pendingOrders', 1)
            ->where('actions.0.kind', 'pesanan')
            ->where('actions.0.target.href', route('orders.show', 'A001')));
        $this->get(route('orders.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('orders/index')
            ->where('status', 'menunggu')
            ->where('counts.menunggu', 1)
            ->has('orders.data', 1)
            ->has('moneyAccounts', 2)
            ->where('shop.url', route('shop.show', $this->business->slug)));
    }

    public function test_the_list_pages_searches_and_keeps_the_queue_oldest_first(): void
    {
        $this->actingAs($this->business->user);

        foreach (range(1, 21) as $minute) {
            $this->travel(1)->minutes();
            $this->post(route('shop.store', $this->business->slug), $this->form(['customer_name' => "Tamu {$minute}"]));
            // The network limit is not what this test is about.
            RateLimiter::clear('shop-orders:127.0.0.1');
        }

        // A queue: the first to order is served first.
        $this->get(route('orders.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('orders.data', 20)
            ->where('orders.meta.total', 21)
            ->where('orders.data.0.code', 'A001')
            ->where('orders.query.urut', 'masuk'));
        $this->get(route('orders.index', ['halaman' => 2]))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.code', 'A021'));
        $this->get(route('orders.index', ['cari' => 'tamu 7']))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.customerName', 'Tamu 7'));

        // History reads newest first, and the tab rides along with the search.
        foreach (['A001', 'A002'] as $code) {
            $this->post(route('orders.reject', $code));
        }
        $this->get(route('orders.index', ['status' => ['ditolak'], 'cari' => 'A00']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('status', 'ditolak')
            ->where('orders.data.0.code', 'A002')
            ->where('orders.query.status', ['ditolak'])
            ->where('orders.query.urut', '-masuk'));
    }

    public function test_owners_reach_only_their_own_orders_and_order_records_stay_locked(): void
    {
        $other = Business::factory()->create();
        $item = Item::factory()->for($other)->create();
        app(Inventory::class)->receive($item, 5, 10000, StockReason::StokAwal, Account::OWNER_CAPITAL);
        $this->actingAs($other->user)->post(route('orders.store'), $this->form(['lines' => [['item_id' => $item->id, 'qty' => 1]]]));

        $this->actingAs($this->business->user)->post(route('orders.store'), $this->form());
        $this->post(route('orders.store'), $this->form());
        $this->post(route('orders.pay', 'A001'), ['amount' => 1000, 'method' => 'tunai']);
        $deposit = $this->business->orders()->where('code', 'A001')->sole()->transactions()->sole();

        // Codes repeat per business: each owner reaches only their own.
        $this->actingAs($other->user);
        $this->get(route('orders.show', 'A001'))->assertInertia(fn (AssertableInertia $page) => $page->where('order.total', 5000));
        $this->get(route('orders.show', 'A002'))->assertNotFound();
        $this->post(route('orders.cancel', 'A002'))->assertNotFound();

        $this->actingAs($this->business->user);
        $this->get(route('transactions.edit', $deposit))->assertRedirect(route('transactions.show', $deposit));
        $this->post(route('transactions.cancel', $deposit))->assertSessionHasErrors('transaction');
        $this->assertSame(TransactionStatus::Posted, $deposit->refresh()->status);
        $this->get(route('transactions.show', $deposit))->assertInertia(fn (AssertableInertia $page) => $page->where('transaction.orderCode', 'A001'));
        // Uang muka is never chosen on the transaction form.
        $this->post(route('transactions.store'), ['type' => 'utang_piutang', 'kind' => 'uang_muka', 'method' => 'tunai', 'amount' => 1000, 'contact_name' => 'Pak Budi'])
            ->assertSessionHasErrors('kind');
    }

    public function test_the_owner_sets_up_the_shop_and_product_photos(): void
    {
        Storage::fake('public');
        $this->actingAs($this->business->user);

        $this->patch(route('orders.shop'), ['accepting_orders' => false, 'whatsapp' => '0812-3456-7890', 'qris' => UploadedFile::fake()->image('qris.png')])
            ->assertSessionHasNoErrors();
        $this->business->refresh();
        Storage::disk('public')->assertExists($this->business->qris_path);
        $this->get(route('shop.show', $this->business->slug))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('shop.open', false)
            ->where('shop.whatsapp', '6281234567890')
            ->where('shop.qrisUrl', asset('storage/'.$this->business->qris_path)));

        $old = $this->business->qris_path;
        $this->patch(route('orders.shop'), ['remove_qris' => true]);
        Storage::disk('public')->assertMissing($old);
        $this->assertNull($this->business->refresh()->qris_path);
        $this->patch(route('orders.shop'), ['qris' => UploadedFile::fake()->create('qris.pdf', 10, 'application/pdf')])->assertSessionHasErrors('qris');

        $this->post(route('items.store'), ['kind' => 'produk', 'name' => 'Kopi Susu', 'unit' => 'gelas', 'price' => 12000, 'photo' => UploadedFile::fake()->image('kopi.jpg')])
            ->assertSessionHasNoErrors();
        $kopi = Item::query()->where('name', 'Kopi Susu')->sole();
        $first = $kopi->photo_path;
        Storage::disk('public')->assertExists($first);

        $fields = ['name' => 'Kopi Susu', 'unit' => 'gelas', 'price' => 12000];
        $this->put(route('items.update', $kopi), $fields + ['photo' => UploadedFile::fake()->image('baru.jpg')])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($first);
        $this->put(route('items.update', $kopi), $fields + ['remove_photo' => true]);
        $this->assertNull($kopi->refresh()->photo_path);
        // A material is never shown in the shop, so it takes no photo.
        $this->post(route('items.store'), ['kind' => 'bahan', 'name' => 'Gula', 'unit' => 'kg', 'photo' => UploadedFile::fake()->image('gula.jpg')])
            ->assertSessionHasErrors('photo');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'customer_name' => 'Pak Budi',
            'customer_phone' => '081234567890',
            'fulfillment' => 'ambil',
            'lines' => [['item_id' => $this->water->id, 'qty' => 1]],
            'website' => '',
        ];
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
