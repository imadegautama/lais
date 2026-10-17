<?php

namespace Tests\Feature;

use App\Enums\ContactType;
use App\Enums\StockReason;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Inventory;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('transactions.index'))->assertRedirect(route('login'));
        $this->get(route('transactions.create'))->assertRedirect(route('login'));
    }

    public function test_the_list_shows_the_owners_transactions_with_totals_that_follow_the_filters(): void
    {
        $business = Business::factory()->create();
        $this->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 500000]);
        $this->record($business, ['type' => 'biaya', 'kind' => 'listrik_air', 'method' => 'tunai', 'amount' => 120000, 'note' => 'token']);
        $this->record($business, ['type' => 'biaya', 'kind' => 'sewa', 'method' => 'pribadi', 'amount' => 300000]);
        $this->record(Business::factory()->create(), ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 999000]);
        $this->actingAs($business->user);

        $this->get(route('transactions.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('transactions/index')
            ->where('hasTransactions', true)
            ->has('transactions.data', 3)
            ->where('transactions.data.0.description', 'Sewa')
            ->where('transactions.data.0.direction', 'none')
            ->where('transactions.summary', ['cashIn' => 500000, 'cashOut' => 120000, 'count' => 3]));

        $this->get(route('transactions.index', ['jenis' => ['biaya'], 'cari' => 'TOKEN']))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.description', 'Listrik & air · token')
            ->where('transactions.summary', ['cashIn' => 0, 'cashOut' => 120000, 'count' => 1]));
    }

    public function test_the_period_filter_keeps_only_recent_transactions(): void
    {
        $business = Business::factory()->create();
        $this->travelTo(now()->subMonths(2));
        $this->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 100000]);
        $this->travelBack();
        $this->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 200000]);

        $this->actingAs($business->user)
            ->get(route('transactions.index', ['periode' => 'bulan_ini']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.query.periode', ['bulan_ini'])
                ->where('transactions.summary.cashIn', 200000));
    }

    public function test_the_form_opens_the_requested_tab_and_lists_contacts_with_their_balance(): void
    {
        $business = Business::factory()->create();
        $water = Item::factory()->for($business)->create(['price' => 4000]);
        $this->record($business, [
            'type' => 'penjualan', 'method' => 'kredit', 'contact_name' => 'Bu Sari',
            'lines' => [['item_id' => $water->id, 'qty' => 2, 'price' => 4000]],
        ]);
        $this->actingAs($business->user);

        $this->get(route('transactions.create', ['jenis' => 'biaya']))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('transactions/create')
            ->where('initialType', 'biaya')
            ->where('contacts.0.name', 'Bu Sari')
            ->where('contacts.0.balance', 8000)
            ->where('items.0.price', 4000));

        $this->get(route('transactions.create', ['jenis' => 'palsu']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('initialType', 'penjualan'));
    }

    public function test_saving_records_the_transaction_and_offers_a_link_to_it(): void
    {
        $business = Business::factory()->create();
        $water = Item::factory()->for($business)->create(['name' => 'Air Mineral', 'price' => 4000]);
        app(Inventory::class)->receive($water, 10, 2500, StockReason::StokAwal, '3101');

        $response = $this->actingAs($business->user)->post(route('transactions.store'), [
            'type' => 'penjualan', 'method' => 'tunai',
            'lines' => [['item_id' => $water->id, 'qty' => 3, 'price' => 4000]],
        ]);

        $transaction = Transaction::query()->sole();
        $response->assertRedirect(route('transactions.index'))
            ->assertInertiaFlash('toast.message', 'Penjualan tersimpan.')
            ->assertInertiaFlash('toast.action.href', route('transactions.show', $transaction));
        $this->assertSame(12000, $transaction->total);
        $this->assertSame('7.000', $water->refresh()->stock);
    }

    public function test_save_and_record_again_returns_to_the_same_tab(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('transactions.store'), ['type' => 'modal', 'kind' => 'prive', 'method' => 'tunai', 'amount' => 50000, 'again' => true])
            ->assertRedirect(route('transactions.create', ['jenis' => 'modal']));
    }

    public function test_a_new_supplier_is_created_from_the_typed_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('transactions.store'), ['type' => 'biaya', 'kind' => 'sewa', 'method' => 'kredit', 'amount' => 750000, 'contact_name' => 'Pak Haji'])
            ->assertRedirect(route('transactions.index'));

        $this->assertSame([ContactType::Supplier, 'Pak Haji'], [Contact::query()->sole()->type, Contact::query()->sole()->name]);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function invalidRecords(): array
    {
        return [
            'sale without items' => [['type' => 'penjualan', 'method' => 'tunai'], 'lines', 'Tambahkan barang yang terjual.'],
            'credit without a contact' => [['type' => 'biaya', 'kind' => 'sewa', 'method' => 'kredit', 'amount' => 1000], 'contact_id', 'Pilih atau tambahkan nama suppliernya.'],
            'personal money on a sale' => [['type' => 'penjualan', 'method' => 'pribadi', 'lines' => [['item_id' => 1, 'qty' => 1, 'price' => 1]]], 'method', 'Cara bayar ini tidak bisa dipakai untuk jenis catatan ini.'],
            'equipment without a name' => [['type' => 'pembelian', 'kind' => 'peralatan', 'method' => 'tunai', 'amount' => 5000], 'note', 'Tulis nama peralatannya, misalnya kompor dua tungku.'],
            'expense without a kind' => [['type' => 'biaya', 'method' => 'tunai', 'amount' => 5000], 'kind', 'Pilih jenis biayanya.'],
            'a date in the future' => [['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 5000, 'occurred_at' => '2099-01-01 08:00'], 'occurred_at', 'Waktunya tidak boleh di masa depan.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidRecords')]
    public function test_invalid_records_are_refused_with_a_clear_message(array $payload, string $field, string $message): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('transactions.store'), $payload)
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_an_item_from_another_business_cannot_be_sold(): void
    {
        $foreign = Item::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('transactions.store'), ['type' => 'penjualan', 'method' => 'tunai', 'lines' => [['item_id' => $foreign->id, 'qty' => 1, 'price' => 5000]]])
            ->assertSessionHasErrors(['lines.0.item_id' => 'Barang ini tidak dijual atau sudah diarsipkan.']);
    }

    public function test_the_detail_shows_lines_stock_changes_and_a_balanced_journal(): void
    {
        $business = Business::factory()->create();
        $water = Item::factory()->for($business)->create(['name' => 'Air Mineral', 'price' => 4000]);
        app(Inventory::class)->receive($water, 10, 2500, StockReason::StokAwal, '3101');
        $sale = $this->record($business, ['type' => 'penjualan', 'method' => 'tunai', 'lines' => [['item_id' => $water->id, 'qty' => 3, 'price' => 4000]]]);

        $journal = $this->actingAs($business->user)
            ->get(route('transactions.show', $sale))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('transactions/show')
                ->where('transaction.amount', 12000)
                ->where('lines.0.hpp', 7500)
                ->where('movements.0.qty', -3)
                ->where('movements.0.stockAfter', 7)
                ->has('journal', 1))
            ->inertiaProps('journal.0.lines');

        $this->assertCount(4, $journal);
        $this->assertSame(array_sum(array_column($journal, 'debit')), array_sum(array_column($journal, 'credit')));
    }

    public function test_another_businesses_transaction_is_not_found(): void
    {
        $foreign = $this->record(Business::factory()->create(), ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 1000]);

        $this->actingAs(User::factory()->create())->get(route('transactions.show', $foreign))->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function record(Business $business, array $data): Transaction
    {
        return app(TransactionService::class)->record($business, $data);
    }
}
