<?php

namespace Tests\Feature;

use App\Enums\ContactType;
use App\Enums\TransactionKind;
use App\Enums\TransactionSource;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Item;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\Debts;
use App\Services\Onboarding;
use App\Services\Orders;
use App\Services\Reports;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DebtTest extends TestCase
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

    public function test_payments_settle_the_oldest_bill_first(): void
    {
        $old = $this->sellOnCredit('Bu Sari', 10000, '2026-11-01 10:00');
        $this->sellOnCredit('Bu Sari', 5000, '2026-11-10 10:00');
        $this->record(['type' => 'utang_piutang', 'kind' => 'terima_piutang', 'method' => 'tunai', 'contact_id' => $old->contact_id, 'amount' => 12000, 'occurred_at' => '2026-11-12 09:00']);

        $book = app(Debts::class)->books($this->business, ContactType::Pelanggan)[$old->contact_id];
        $this->assertSame([0, 3000], array_column($book['bills'], 'remaining'));
        $this->assertSame(3000, $book['balance']);

        // The age runs from the bill still open, not the first sale.
        $this->actingAs($this->business->user)->get(route('debts.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('debts/index')
            ->where('tab', 'piutang')
            ->has('rows', 1)
            ->where('rows.0.name', 'Bu Sari')
            ->where('rows.0.balance', 3000)
            ->where('rows.0.ageDays', 8)
            ->where('totals.piutang', ['balance' => 3000, 'contacts' => 1, 'oldestDays' => 8])
            ->where('totals.utang.contacts', 1));
    }

    public function test_each_tab_lists_only_open_balances_of_this_business_oldest_first(): void
    {
        $this->sellOnCredit('Pak Budi', 8000, '2026-11-15 10:00');
        $this->sellOnCredit('Bu Sari', 4000, '2026-11-05 10:00');
        $paid = $this->sellOnCredit('Lunas', 4000, '2026-11-02 10:00');
        $this->record(['type' => 'utang_piutang', 'kind' => 'terima_piutang', 'method' => 'tunai', 'contact_id' => $paid->contact_id, 'amount' => 4000]);
        app(TransactionService::class)->cancel($this->sellOnCredit('Batal', 4000, '2026-11-03 10:00'));
        $other = Business::factory()->create();
        app(TransactionService::class)->record($other, ['type' => 'biaya', 'kind' => 'sewa', 'method' => 'kredit', 'amount' => 50000, 'contact_name' => 'Orang lain']);
        $this->actingAs($this->business->user);

        $this->get(route('debts.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.0.name', 'Bu Sari')
            ->where('rows.1.name', 'Pak Budi')
            ->has('rows', 2));

        // The opening debt to Toko Sumber is the only payable.
        $this->get(route('debts.index', ['jenis' => 'utang']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tab', 'utang')
            ->has('rows', 1)
            ->where('rows.0.name', 'Toko Sumber')
            ->where('rows.0.balance', 300000));
    }

    public function test_the_owner_pays_from_the_debts_page(): void
    {
        $sale = $this->sellOnCredit('Bu Sari', 10000, '2026-11-10 10:00');
        $supplier = Contact::query()->where('name', 'Toko Sumber')->sole();
        $this->actingAs($this->business->user);

        $this->post(route('debts.pay', $sale->contact_id), ['amount' => 10001, 'method' => 'tunai'])->assertSessionHasErrors('amount');
        $this->post(route('debts.pay', $sale->contact_id), ['amount' => 4000, 'method' => 'tunai'])->assertSessionHasNoErrors();
        $this->post(route('debts.pay', $supplier), ['amount' => 100000, 'method' => 'non_tunai'])->assertSessionHasNoErrors();
        $this->post(route('debts.pay', $supplier), ['amount' => 1000, 'method' => 'kredit'])->assertSessionHasErrors('method');

        $this->assertSame(
            [TransactionKind::TerimaPiutang->value, TransactionKind::BayarUtang->value],
            Transaction::query()->where('type', 'utang_piutang')->orderBy('id')->pluck('kind')->map->value->all(),
        );
        $this->get(route('debts.show', $supplier))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('debts/show')
            ->where('contact.name', 'Toko Sumber')
            ->where('balance', 200000)
            ->where('payments.0.amount', 100000)
            ->where('payments.0.moneyAccount', 'BCA'));
        $this->assertTrue(app(Reports::class)->balanceSheet($this->business, now())['balanced']);

        $other = Business::factory()->create()->contacts()->create(['type' => 'pelanggan', 'name' => 'X']);
        $this->get(route('debts.show', $other))->assertNotFound();
        $this->post(route('debts.pay', $other), ['amount' => 1000, 'method' => 'tunai'])->assertNotFound();
    }

    public function test_an_order_paid_from_the_debts_page_shows_as_paid(): void
    {
        // An older tab, then an order done with 12.000 still unpaid.
        $older = $this->sellOnCredit('Pak Budi', 5000, '2026-11-01 10:00');
        $order = $this->doneOrder();
        $orders = app(Orders::class);
        $this->assertSame(0, $orders->paid($order));

        // Paid from the order: it settles that order, not the older tab.
        $this->actingAs($this->business->user)->post(route('orders.pay', $order->code), ['amount' => 2000, 'method' => 'tunai'])->assertSessionHasNoErrors();
        $this->assertSame(2000, $orders->paid($order));

        // Paid from the debts page: the older tab first, then the order.
        $this->post(route('debts.pay', $older->contact_id), ['amount' => 15000, 'method' => 'tunai'])->assertSessionHasNoErrors();
        $this->assertSame(12000, $orders->paid($order->refresh()));
        $this->post(route('orders.pay', $order->code), ['amount' => 1, 'method' => 'tunai'])->assertSessionHasErrors('amount');

        $this->get(route('orders.show', $order->code))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('order.paymentStatus', 'lunas')
            ->where('order.paidElsewhere', 10000));
        $this->get(route('debts.show', $older->contact_id))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('balance', 0)
            // All paid, so newest first.
            ->where('bills.0.orderCode', 'A001')
            ->where('bills.1.orderCode', null));
    }

    public function test_the_dashboard_sends_an_overdue_tab_to_its_bills(): void
    {
        $sale = $this->sellOnCredit('Bu Sari', 10000, '2026-11-01 10:00');

        $this->actingAs($this->business->user)->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('actions.0.kind', 'piutang')
            ->where('actions.0.target.href', route('debts.show', $sale->contact_id)));
    }

    private function doneOrder(): Order
    {
        $orders = app(Orders::class);
        $order = $orders->place($this->business, [
            'customer_name' => 'Pak Budi',
            'customer_phone' => '081234567890',
            'fulfillment' => 'ambil',
            'lines' => [['item_id' => $this->water->id, 'qty' => 3]],
        ], TransactionSource::Web);
        // Placed by the owner, so it joins Pak Budi's tab by name.
        $orders->complete($order->refresh());

        return $order->refresh();
    }

    private function sellOnCredit(string $name, int $amount, string $at): Transaction
    {
        $contact = $this->business->contacts()->firstOrCreate(['type' => 'pelanggan', 'name' => $name]);

        return $this->record(['type' => 'penjualan', 'method' => 'kredit', 'occurred_at' => $at, 'contact_id' => $contact->id, 'lines' => [['item_id' => $this->water->id, 'qty' => 1, 'price' => $amount]]]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function record(array $data): Transaction
    {
        return app(TransactionService::class)->record($this->business, $data);
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
            'debts' => [['name' => 'Toko Sumber', 'amount' => 300000]],
        ]);

        return $business->refresh();
    }
}
