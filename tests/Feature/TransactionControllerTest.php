<?php

namespace Tests\Feature;

use App\Enums\ContactType;
use App\Enums\StockReason;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Item;
use App\Models\MoneyAccount;
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
        $this->travelTo(now()->subMonthsNoOverflow(2));
        $this->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 100000]);
        $this->travelBack();
        $this->travelTo(now()->subMonthNoOverflow());
        $this->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 150000]);
        $this->travelBack();
        $this->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 200000]);

        $this->actingAs($business->user)
            ->get(route('transactions.index', ['periode' => 'bulan_ini']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.query.periode', ['bulan_ini'])
                ->where('transactions.summary.cashIn', 200000));

        // Last month stops where this month begins.
        $this->get(route('transactions.index', ['periode' => 'bulan_lalu']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.summary.cashIn', 150000));
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

    public function test_the_edit_form_starts_from_the_record_with_stock_and_balance_as_if_it_were_cancelled(): void
    {
        [$business, $water, $sale] = $this->creditSale();

        $this->actingAs($business->user)->get(route('transactions.edit', $sale))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('transactions/create')
            ->where('initialType', 'penjualan')
            ->where('editing', ['id' => $sale->id, 'description' => '3 Air Mineral', 'proofUrl' => null])
            ->where('initial.method', 'kredit')
            ->where('initial.paid_amount', 2000)
            ->where('initial.contact', (string) $sale->contact_id)
            ->where('initial.lines', [['item_id' => (string) $water->id, 'qty' => '3', 'price' => 4000]])
            ->where('items.0.stock', 10)
            ->where('items.0.stockValue', 25000)
            ->where('contacts.0.balance', 0));
    }

    public function test_saving_an_edit_replaces_the_record_and_opens_the_new_one(): void
    {
        [$business, $water, $sale] = $this->creditSale();

        $response = $this->actingAs($business->user)->put(route('transactions.update', $sale), [
            'type' => 'penjualan', 'method' => 'kredit', 'paid_amount' => 2000, 'contact_id' => $sale->contact_id,
            'lines' => [['item_id' => $water->id, 'qty' => 2, 'price' => 4000]],
        ]);

        $replacement = Transaction::query()->where('status', 'posted')->sole();
        $response->assertRedirect(route('transactions.show', $replacement))->assertInertiaFlash('toast.message', 'Perubahan disimpan.');
        $this->assertSame('8.000', $water->refresh()->stock);
        $this->get(route('transactions.show', $sale))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('transaction.voided', true)
            ->where('transaction.replacedById', $replacement->id));
        $this->get(route('transactions.show', $replacement))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('transaction.replacesId', $sale->id));
    }

    public function test_cancelling_keeps_the_record_with_its_reason_and_both_journals(): void
    {
        [$business, $water, $sale] = $this->creditSale();
        $this->actingAs($business->user);

        $this->post(route('transactions.cancel', $sale), ['reason' => 'Tercatat dua kali'])
            ->assertRedirect(route('transactions.show', $sale))
            ->assertInertiaFlash('toast.message', 'Catatan dibatalkan.');

        $this->assertSame('10.000', $water->refresh()->stock);
        $this->get(route('transactions.show', $sale))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('transaction.voided', true)
            ->where('transaction.voidReason', 'Tercatat dua kali')
            ->has('journal', 2)
            ->has('movements', 2));

        $this->post(route('transactions.cancel', $sale))->assertSessionHasErrors(['transaction' => 'Catatan ini sudah dibatalkan.']);
        $this->get(route('transactions.edit', $sale))
            ->assertRedirect(route('transactions.show', $sale))
            ->assertInertiaFlash('toast.message', 'Catatan ini sudah dibatalkan.');
    }

    public function test_another_businesses_transaction_cannot_be_edited_or_cancelled(): void
    {
        $foreign = $this->record(Business::factory()->create(), ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 1000]);
        $this->actingAs(User::factory()->create());

        $this->get(route('transactions.edit', $foreign))->assertNotFound();
        $this->put(route('transactions.update', $foreign), ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 5000])->assertNotFound();
        $this->post(route('transactions.cancel', $foreign))->assertNotFound();
        $this->assertSame([1, 0], [Transaction::query()->count(), Transaction::query()->where('status', 'void')->count()]);
    }

    public function test_non_cash_asks_for_the_rekening_only_when_there_are_several(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($business->user);
        $setor = ['type' => 'modal', 'kind' => 'setor', 'method' => 'non_tunai', 'amount' => 25_000];

        $this->post(route('transactions.store'), $setor)->assertSessionHasNoErrors();
        $gopay = MoneyAccount::factory()->for($business)->create(['name' => 'GoPay']);
        $this->post(route('transactions.store'), $setor)->assertSessionHasErrors(['money_account_id' => 'Pilih rekeningnya.']);
        $this->post(route('transactions.store'), $setor + ['money_account_id' => $business->cashBox()->id])
            ->assertSessionHasErrors(['money_account_id' => 'Rekening tidak ditemukan atau sudah diarsipkan.']);
        $this->post(route('transactions.store'), $setor + ['money_account_id' => $gopay->id])->assertSessionHasNoErrors();

        $this->get(route('transactions.create'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('moneyAccounts', 3)
            ->where('moneyAccounts.2', ['id' => $gopay->id, 'kind' => 'non_tunai', 'name' => 'GoPay', 'balance' => 25_000]));
    }

    public function test_a_transfer_is_recorded_from_the_form_and_shows_both_rekening(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($business->user);
        $bca = $business->moneyAccounts()->where('kind', 'non_tunai')->sole();
        $bca->update(['name' => 'BCA']);

        $this->post(route('transactions.store'), ['type' => 'pindah_dana', 'money_account_id' => $business->cashBox()->id, 'to_money_account_id' => $business->cashBox()->id, 'amount' => 1000])
            ->assertSessionHasErrors(['to_money_account_id' => 'Rekening asal dan tujuan harus berbeda.']);
        $this->post(route('transactions.store'), ['type' => 'pindah_dana', 'method' => 'tunai', 'money_account_id' => $business->cashBox()->id, 'to_money_account_id' => $bca->id, 'amount' => 100_000])
            ->assertSessionHasErrors('method');
        $this->post(route('transactions.store'), ['type' => 'pindah_dana', 'money_account_id' => $business->cashBox()->id, 'to_money_account_id' => $bca->id, 'amount' => 100_000])
            ->assertInertiaFlash('toast.message', 'Pindah dana tersimpan.');

        $transfer = Transaction::query()->sole();
        $this->get(route('transactions.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('transactions.data.0.description', 'Setor tunai ke BCA')
            ->where('transactions.data.0.moneyAccount', 'Kas tunai')
            ->where('transactions.data.0.toMoneyAccount', 'BCA')
            ->where('transactions.data.0.direction', 'none'));
        $this->get(route('transactions.edit', $transfer))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('initial.money_account_id', (string) $business->cashBox()->id)
            ->where('initial.to_money_account_id', (string) $bca->id)
            // As if the transfer were cancelled, so the preview starts from before it.
            ->where('moneyAccounts.0.balance', 0));
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function invalidOpeningBalances(): array
    {
        return [
            'cash without its rekening' => [['kind' => 'saldo_kas', 'amount' => 1000], 'money_account_id', 'Pilih rekeningnya.'],
            'equipment without a name' => [['kind' => 'saldo_peralatan', 'amount' => 1000], 'note', 'Tulis nama peralatannya, misalnya kompor dua tungku.'],
            'a debt without its supplier' => [['kind' => 'saldo_utang', 'amount' => 1000], 'contact_id', 'Pilih atau tambahkan nama suppliernya.'],
            'a method to choose' => [['kind' => 'saldo_peralatan', 'amount' => 1000, 'note' => 'Etalase', 'method' => 'tunai'], 'method', 'Cara bayarnya mengikuti rekening atau jenis catatan ini, jadi tidak perlu dipilih.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidOpeningBalances')]
    public function test_an_opening_balance_asks_for_what_its_kind_needs(array $payload, string $field, string $message): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('transactions.store'), ['type' => 'saldo_awal'] + $payload)
            ->assertSessionHasErrors([$field => $message]);
    }

    public function test_opening_balances_are_not_counted_as_money_coming_in(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($business->user);
        $this->record($business, ['type' => 'saldo_awal', 'kind' => 'saldo_kas', 'amount' => 900_000, 'money_account_id' => $business->cashBox()->id]);
        $this->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 100_000]);

        $this->get(route('transactions.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('transactions.summary', ['cashIn' => 100_000, 'cashOut' => 0, 'count' => 2]));
        $this->get(route('transactions.create', ['jenis' => 'saldo_awal']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('initialType', 'saldo_awal'));
    }

    /**
     * Three of ten bottles sold on credit to Bu Sari, Rp2.000 paid on the spot.
     *
     * @return array{0: Business, 1: Item, 2: Transaction}
     */
    private function creditSale(): array
    {
        $business = Business::factory()->create();
        $water = Item::factory()->for($business)->create(['name' => 'Air Mineral', 'price' => 4000]);
        app(Inventory::class)->receive($water, 10, 2500, StockReason::StokAwal, '3101');
        $sale = $this->record($business, [
            'type' => 'penjualan', 'method' => 'kredit', 'paid_amount' => 2000, 'contact_name' => 'Bu Sari',
            'lines' => [['item_id' => $water->id, 'qty' => 3, 'price' => 4000]],
        ]);

        return [$business, $water, $sale];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function test_the_form_can_open_on_a_choice_inside_a_tab(): void
    {
        $this->actingAs(Business::factory()->create()->user);

        $this->get(route('transactions.create', ['jenis' => 'pembelian', 'kategori' => 'peralatan']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('initialType', 'pembelian')->where('initialKind', 'peralatan'));

        // A choice of another tab is ignored.
        $this->get(route('transactions.create', ['jenis' => 'biaya', 'kategori' => 'peralatan']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('initialType', 'biaya')->where('initialKind', null));
    }

    private function record(Business $business, array $data): Transaction
    {
        return app(TransactionService::class)->record($business, $data);
    }
}
