<?php

namespace Tests\Feature;

use App\Enums\ContactType;
use App\Enums\StockReason;
use App\Enums\TransactionStatus;
use App\Models\Business;
use App\Models\Item;
use App\Models\MoneyAccount;
use App\Models\Transaction;
use App\Services\Inventory;
use App\Services\Ledger;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_cash_sale_books_revenue_and_hpp_and_lowers_stock(): void
    {
        $business = Business::factory()->create();
        $water = $this->stocked($business, 'Air Mineral', 10, 2500, price: 4000);

        $sale = $this->record($business, ['type' => 'penjualan', 'method' => 'tunai', 'lines' => [['item_id' => $water->id, 'qty' => 3, 'price' => 4000]]]);

        $this->assertSame([1101 => 12000, 1301 => -7500, 4101 => -12000, 5101 => 7500], $this->journal($sale));
        $this->assertSame('3 Air Mineral', $sale->description);
        $this->assertSame(12000, $sale->cash_flow);
        $this->assertSame(7500, $sale->lines()->sole()->hpp);
        $this->assertSame('7.000', $water->refresh()->stock);
    }

    public function test_selling_a_recipe_product_lowers_each_ingredient_by_recipe_times_quantity(): void
    {
        $business = Business::factory()->create();
        $rice = $this->stocked($business, 'Beras', 10, 14000, material: true);
        $egg = $this->stocked($business, 'Telur', 30, 2200, material: true);
        $friedRice = Item::factory()->recipe()->for($business)->create(['name' => 'Nasi Goreng', 'price' => 15000]);
        $friedRice->recipeLines()->createMany([['material_id' => $rice->id, 'qty' => 0.15], ['material_id' => $egg->id, 'qty' => 1]]);

        $sale = $this->record($business, ['type' => 'penjualan', 'method' => 'non_tunai', 'lines' => [['item_id' => $friedRice->id, 'qty' => 2, 'price' => 15000]]]);

        $this->assertSame('9.700', $rice->refresh()->stock);
        $this->assertSame('28.000', $egg->refresh()->stock);
        // 0,3 kg × Rp14.000 + 2 × Rp2.200
        $this->assertSame(8600, $sale->lines()->sole()->hpp);
        $this->assertSame([1102 => 30000, 1301 => -8600, 4101 => -30000, 5101 => 8600], $this->journal($sale));
    }

    public function test_a_sale_may_drive_stock_negative_and_the_next_purchase_books_the_gap_to_hpp(): void
    {
        $business = Business::factory()->create();
        $chips = $this->stocked($business, 'Kerupuk', 1, 2000, price: 5000);

        $sale = $this->record($business, ['type' => 'penjualan', 'method' => 'tunai', 'lines' => [['item_id' => $chips->id, 'qty' => 3, 'price' => 5000]]]);

        $this->assertSame('-2.000', $chips->refresh()->stock);
        $this->assertSame(0, $chips->stock_value);
        $this->assertSame(2000, $sale->lines()->sole()->hpp);

        $purchase = $this->record($business, ['type' => 'pembelian', 'kind' => 'barang', 'method' => 'tunai', 'lines' => [['item_id' => $chips->id, 'qty' => 10, 'price' => 2500]]]);

        // The 2 units already sold take their cost now; the 8 left start a fresh average.
        $this->assertSame([1101 => -25000, 1301 => 20000, 5101 => 5000], $this->journal($purchase));
        $this->assertSame(20000, $chips->refresh()->stock_value);
        $this->assertSame(2500.0, $chips->averageCost());
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: int}>
     */
    public static function purchaseMethods(): array
    {
        return [
            'tunai' => ['tunai', 1101, -72000],
            'non-tunai' => ['non_tunai', 1102, -72000],
            'belum dibayar' => ['kredit', 2101, 0],
            'uang pribadi' => ['pribadi', 3101, 0],
        ];
    }

    #[DataProvider('purchaseMethods')]
    public function test_a_purchase_updates_the_moving_average_and_credits_the_chosen_account(string $method, int $account, int $cashFlow): void
    {
        $business = Business::factory()->create();
        $egg = $this->stocked($business, 'Telur', 30, 2000, material: true);

        $purchase = $this->record($business, [
            'type' => 'pembelian', 'kind' => 'barang', 'method' => $method, 'contact_name' => 'Toko Sumber Rejeki',
            'lines' => [['item_id' => $egg->id, 'qty' => 30, 'price' => 2400]],
        ]);

        $expected = [1301 => 72000, $account => -72000];
        ksort($expected);
        $this->assertSame($expected, $this->journal($purchase));
        $this->assertSame(2200.0, $egg->refresh()->averageCost());
        $this->assertSame($cashFlow, $purchase->cash_flow);
        $this->assertSame('30 butir Telur', $purchase->description);
    }

    public function test_an_equipment_purchase_debits_equipment(): void
    {
        $business = Business::factory()->create();

        $purchase = $this->record($business, ['type' => 'pembelian', 'kind' => 'peralatan', 'method' => 'tunai', 'amount' => 350000, 'note' => 'Kompor dua tungku']);

        $this->assertSame([1101 => -350000, 1401 => 350000], $this->journal($purchase));
        $this->assertSame('Peralatan: Kompor dua tungku', $purchase->description);
    }

    public function test_an_expense_paid_from_personal_money_counts_as_owner_capital(): void
    {
        $business = Business::factory()->create();

        $expense = $this->record($business, ['type' => 'biaya', 'kind' => 'listrik_air', 'method' => 'pribadi', 'amount' => 120000, 'note' => 'token Oktober']);

        $this->assertSame([3101 => -120000, 6101 => 120000], $this->journal($expense));
        $this->assertSame(0, $expense->cash_flow);
        $this->assertSame('Listrik & air · token Oktober', $expense->description);
    }

    public function test_a_partly_paid_credit_sale_splits_cash_and_receivable_and_payments_reduce_it(): void
    {
        $business = Business::factory()->create();
        $water = $this->stocked($business, 'Air Mineral', 10, 2500, price: 15000);

        $sale = $this->record($business, [
            'type' => 'penjualan', 'method' => 'kredit', 'paid_amount' => 10000, 'contact_name' => 'Bu Sari',
            'lines' => [['item_id' => $water->id, 'qty' => 2, 'price' => 15000]],
        ]);

        $this->assertSame([1101 => 10000, 1201 => 20000, 1301 => -5000, 4101 => -30000, 5101 => 5000], $this->journal($sale));
        $this->assertSame(ContactType::Pelanggan, $sale->contact->type);
        $this->assertSame([$sale->contact_id => 20000], app(Ledger::class)->contactBalances($business));

        $payment = $this->record($business, ['type' => 'utang_piutang', 'kind' => 'terima_piutang', 'method' => 'tunai', 'contact_id' => $sale->contact_id, 'amount' => 15000]);

        $this->assertSame([1101 => 15000, 1201 => -15000], $this->journal($payment));
        $this->assertSame('Terima bayaran dari Bu Sari', $payment->description);
        $this->assertSame(5000, app(Ledger::class)->contactBalances($business)[$sale->contact_id]);

        $this->assertValidationError('amount', 'Sisa tagihan Bu Sari Rp5.000. Nominal tidak boleh lebih dari itu.', fn () => $this->record($business, [
            'type' => 'utang_piutang', 'kind' => 'terima_piutang', 'method' => 'tunai', 'contact_id' => $sale->contact_id, 'amount' => 6000,
        ]));
    }

    public function test_a_credit_purchase_creates_a_payable_that_paying_clears(): void
    {
        $business = Business::factory()->create();
        $egg = $this->stocked($business, 'Telur', 10, 2000, material: true);

        $purchase = $this->record($business, [
            'type' => 'pembelian', 'kind' => 'barang', 'method' => 'kredit', 'contact_name' => 'Toko Makmur',
            'lines' => [['item_id' => $egg->id, 'qty' => 25, 'price' => 2000]],
        ]);
        $this->assertSame(ContactType::Supplier, $purchase->contact->type);

        $payment = $this->record($business, ['type' => 'utang_piutang', 'kind' => 'bayar_utang', 'method' => 'non_tunai', 'contact_id' => $purchase->contact_id, 'amount' => 50000]);

        $this->assertSame([1102 => -50000, 2101 => 50000], $this->journal($payment));
        $this->assertSame([$purchase->contact_id => 0], app(Ledger::class)->contactBalances($business));
        $this->assertSame(0, app(Ledger::class)->balance($business, '2101'));
    }

    public function test_paying_a_contact_who_owes_nothing_is_refused(): void
    {
        $business = Business::factory()->create();
        $supplier = $business->contacts()->create(['type' => 'supplier', 'name' => 'Toko Lunas']);

        $this->assertValidationError('contact_id', 'Toko Lunas tidak punya tagihan yang belum dibayar.', fn () => $this->record($business, [
            'type' => 'utang_piutang', 'kind' => 'bayar_utang', 'method' => 'tunai', 'contact_id' => $supplier->id, 'amount' => 1000,
        ]));
    }

    public function test_an_owner_drawing_is_prive_and_leaves_profit_untouched(): void
    {
        $business = Business::factory()->create();
        $this->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 1000000]);

        $drawing = $this->record($business, ['type' => 'modal', 'kind' => 'prive', 'method' => 'tunai', 'amount' => 200000, 'note' => 'buat rumah']);

        $this->assertSame([1101 => -200000, 3201 => 200000], $this->journal($drawing));
        $this->assertSame('Ambil untuk pribadi · buat rumah', $drawing->description);
        $this->assertSame(800000, app(Ledger::class)->balance($business, '1101'));

        foreach (['4101', '5101', '6101'] as $profitAccount) {
            $this->assertSame(0, app(Ledger::class)->balance($business, $profitAccount));
        }
    }

    public function test_a_credit_sale_paid_in_full_is_refused(): void
    {
        $business = Business::factory()->create();
        $water = $this->stocked($business, 'Air Mineral', 10, 2500, price: 4000);

        $this->assertValidationError('paid_amount', 'Kalau sudah dibayar penuh, pilih Tunai atau Non-tunai.', fn () => $this->record($business, [
            'type' => 'penjualan', 'method' => 'kredit', 'paid_amount' => 4000, 'contact_name' => 'Pak Budi',
            'lines' => [['item_id' => $water->id, 'qty' => 1, 'price' => 4000]],
        ]));
        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_cancelling_a_recipe_sale_returns_each_ingredient_and_nets_the_journal_to_zero(): void
    {
        $business = Business::factory()->create();
        $rice = $this->stocked($business, 'Beras', 10, 14000, material: true);
        $egg = $this->stocked($business, 'Telur', 30, 2200, material: true);
        $friedRice = Item::factory()->recipe()->for($business)->create(['name' => 'Nasi Goreng', 'price' => 15000]);
        $friedRice->recipeLines()->createMany([['material_id' => $rice->id, 'qty' => 0.15], ['material_id' => $egg->id, 'qty' => 1]]);
        $sale = $this->record($business, ['type' => 'penjualan', 'method' => 'non_tunai', 'occurred_at' => '2026-10-15 09:30', 'lines' => [['item_id' => $friedRice->id, 'qty' => 2, 'price' => 15000]]]);

        $this->service()->cancel($sale, 'Salah input');

        $this->assertSame(['10.000', 140000], [$rice->refresh()->stock, $rice->stock_value]);
        $this->assertSame(['30.000', 66000], [$egg->refresh()->stock, $egg->stock_value]);
        $this->assertSame([], array_filter($this->journal($sale)));
        $this->assertSame(TransactionStatus::Void, $sale->refresh()->status);
        $this->assertSame('Salah input', $sale->void_reason);
        $this->assertNotNull($sale->voided_at);
        // Mirrored on the sale's own date, so that day nets to zero.
        $this->assertSame(['2026-10-15 09:30'], $sale->journalEntries()->get()->map(fn ($entry) => $entry->occurred_at->format('Y-m-d H:i'))->unique()->values()->all());
        $this->assertSame(2, $sale->stockMovements()->where('reason', StockReason::Pembatalan)->count());
    }

    public function test_cancelling_a_purchase_restores_the_previous_average(): void
    {
        $business = Business::factory()->create();
        $egg = $this->stocked($business, 'Telur', 30, 2200, material: true);
        $purchase = $this->record($business, ['type' => 'pembelian', 'kind' => 'barang', 'method' => 'tunai', 'lines' => [['item_id' => $egg->id, 'qty' => 10, 'price' => 2560]]]);
        $this->assertSame(2290.0, $egg->refresh()->averageCost());

        $this->service()->cancel($purchase);

        $this->assertSame(['30.000', 66000], [$egg->refresh()->stock, $egg->stock_value]);
        $this->assertSame(2200.0, $egg->averageCost());
        $this->assertSame([], array_filter($this->journal($purchase)));
    }

    public function test_cancelling_a_purchase_whose_units_were_sold_books_hpp_as_if_it_never_happened(): void
    {
        $business = Business::factory()->create();
        $chips = $this->stocked($business, 'Kerupuk', 2, 2000, price: 5000);
        $purchase = $this->record($business, ['type' => 'pembelian', 'kind' => 'barang', 'method' => 'tunai', 'lines' => [['item_id' => $chips->id, 'qty' => 10, 'price' => 2500]]]);
        $this->record($business, ['type' => 'penjualan', 'method' => 'tunai', 'lines' => [['item_id' => $chips->id, 'qty' => 11, 'price' => 5000]]]);

        $this->service()->cancel($purchase);

        $this->assertSame(['-9.000', 0], [$chips->refresh()->stock, $chips->stock_value]);
        // Only the 2 opening units had a cost; the other 9 were sold past zero.
        $this->assertSame(4000, app(Ledger::class)->balance($business, '5101'));
        $this->assertSame(0, app(Ledger::class)->balance($business, '1301'));
    }

    public function test_cancelling_an_oversold_sale_undoes_the_hpp_the_next_purchase_caught_up(): void
    {
        $business = Business::factory()->create();
        $chips = $this->stocked($business, 'Kerupuk', 1, 2000, price: 5000);
        $sale = $this->record($business, ['type' => 'penjualan', 'method' => 'tunai', 'lines' => [['item_id' => $chips->id, 'qty' => 3, 'price' => 5000]]]);
        $this->record($business, ['type' => 'pembelian', 'kind' => 'barang', 'method' => 'tunai', 'lines' => [['item_id' => $chips->id, 'qty' => 10, 'price' => 2500]]]);

        $this->service()->cancel($sale);

        // 1 opening unit at Rp2.000 + 10 bought at Rp2.500, as if nothing was sold.
        $this->assertSame(['11.000', 27000], [$chips->refresh()->stock, $chips->stock_value]);
        $this->assertSame(0, app(Ledger::class)->balance($business, '5101'));
        $this->assertSame(27000, app(Ledger::class)->balance($business, '1301'));
    }

    public function test_a_credit_sale_with_a_payment_against_it_cannot_be_cancelled_first(): void
    {
        $business = Business::factory()->create();
        $water = $this->stocked($business, 'Air Mineral', 10, 2500, price: 15000);
        $sale = $this->record($business, [
            'type' => 'penjualan', 'method' => 'kredit', 'paid_amount' => 10000, 'contact_name' => 'Bu Sari',
            'lines' => [['item_id' => $water->id, 'qty' => 2, 'price' => 15000]],
        ]);
        $payment = $this->record($business, ['type' => 'utang_piutang', 'kind' => 'terima_piutang', 'method' => 'tunai', 'contact_id' => $sale->contact_id, 'amount' => 15000]);

        $this->assertValidationError('transaction', 'Bu Sari sudah membayar sebagian tagihan ini, jadi sisa tagihannya akan minus Rp15.000. Batalkan pembayarannya dulu.', fn () => $this->service()->cancel($sale));
        $this->assertSame(TransactionStatus::Posted, $sale->refresh()->status);
        $this->assertSame('8.000', $water->refresh()->stock);

        $this->service()->cancel($payment);
        $this->service()->cancel($sale);

        $this->assertSame([$sale->contact_id => 0], app(Ledger::class)->contactBalances($business));
        $this->assertSame('10.000', $water->refresh()->stock);
    }

    public function test_a_transaction_is_cancelled_only_once(): void
    {
        $business = Business::factory()->create();
        $drawing = $this->record($business, ['type' => 'modal', 'kind' => 'prive', 'method' => 'tunai', 'amount' => 50000]);
        $this->service()->cancel($drawing);

        $this->assertValidationError('transaction', 'Catatan ini sudah dibatalkan.', fn () => $this->service()->cancel($drawing));
        $this->assertSame(2, $drawing->journalEntries()->count());
    }

    public function test_a_sale_of_an_archived_item_cannot_be_cancelled(): void
    {
        $business = Business::factory()->create();
        $water = $this->stocked($business, 'Air Mineral', 10, 2500, price: 4000);
        $sale = $this->record($business, ['type' => 'penjualan', 'method' => 'tunai', 'lines' => [['item_id' => $water->id, 'qty' => 1, 'price' => 4000]]]);
        $water->delete();

        $this->assertValidationError('transaction', 'Air Mineral sudah diarsipkan, jadi stoknya tidak bisa dikembalikan.', fn () => $this->service()->cancel($sale));
    }

    public function test_editing_records_the_new_version_and_links_it_to_the_old_one(): void
    {
        $business = Business::factory()->create();
        $water = $this->stocked($business, 'Air Mineral', 10, 2500, price: 15000);
        $credit = fn (int $price) => [
            'type' => 'penjualan', 'method' => 'kredit', 'paid_amount' => 10000, 'contact_name' => 'Bu Sari',
            'lines' => [['item_id' => $water->id, 'qty' => 2, 'price' => $price]],
        ];
        $sale = $this->record($business, $credit(15000));
        $this->record($business, ['type' => 'utang_piutang', 'kind' => 'terima_piutang', 'method' => 'tunai', 'contact_id' => $sale->contact_id, 'amount' => 15000]);

        // Rp20.000 − Rp10.000 paid on the spot leaves less than the Rp15.000 already paid later.
        $this->assertValidationError('transaction', 'Bu Sari sudah membayar sebagian tagihan ini, jadi sisa tagihannya akan minus Rp5.000. Batalkan pembayarannya dulu.', fn () => $this->service()->replace($sale, $credit(10000)));
        $this->assertSame(TransactionStatus::Posted, $sale->refresh()->status);
        $this->assertSame(2, Transaction::query()->count());

        $replacement = $this->service()->replace($sale, $credit(14000));

        $this->assertSame([TransactionStatus::Void, $replacement->id], [$sale->refresh()->status, $sale->replaced_by_id]);
        $this->assertTrue($replacement->replaces->is($sale));
        $this->assertSame(28000, $replacement->total);
        $this->assertSame(3000, app(Ledger::class)->contactBalances($business)[$sale->contact_id]);
        $this->assertSame('8.000', $water->refresh()->stock);
    }

    public function test_editing_a_payment_may_use_the_balance_it_frees(): void
    {
        $business = Business::factory()->create();
        $supplier = $this->record($business, ['type' => 'biaya', 'kind' => 'sewa', 'method' => 'kredit', 'contact_name' => 'Pak Kos', 'amount' => 50000])->contact_id;
        $payment = $this->record($business, ['type' => 'utang_piutang', 'kind' => 'bayar_utang', 'method' => 'tunai', 'contact_id' => $supplier, 'amount' => 30000]);

        $this->service()->replace($payment, ['type' => 'utang_piutang', 'kind' => 'bayar_utang', 'method' => 'tunai', 'contact_id' => $supplier, 'amount' => 50000]);

        $this->assertSame([$supplier => 0], app(Ledger::class)->contactBalances($business));
        $this->assertSame(-50000, app(Ledger::class)->balance($business, '1101'));
    }

    public function test_non_cash_money_lands_on_the_chosen_rekening_and_asks_when_there_are_several(): void
    {
        $business = Business::factory()->create();
        $bca = $business->moneyAccounts()->where('kind', 'non_tunai')->sole();
        $modal = fn (array $extra = []) => $this->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'non_tunai', 'amount' => 100_000] + $extra);

        // One rekening: no need to ask.
        $this->assertSame($bca->id, $modal()->money_account_id);

        $gopay = MoneyAccount::factory()->for($business)->create(['name' => 'GoPay']);
        $this->assertValidationError('money_account_id', 'Pilih rekeningnya.', fn () => $modal());
        $this->assertSame($gopay->id, $modal(['money_account_id' => $gopay->id])->money_account_id);
        $this->assertSame($business->cashBox()->id, $this->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 5000])->money_account_id);

        $this->assertSame([$business->cashBox()->id => 5000, $bca->id => 100_000, $gopay->id => 100_000], app(Ledger::class)->moneyBalances($business));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function transfers(): array
    {
        return [
            'setor tunai' => ['tunai', 'bca', 'Setor tunai ke BCA'],
            'tarik tunai' => ['bca', 'tunai', 'Tarik tunai dari BCA'],
            'antar rekening' => ['bca', 'gopay', 'Pindah dari BCA ke GoPay'],
        ];
    }

    #[DataProvider('transfers')]
    public function test_a_transfer_moves_money_between_rekening_without_changing_business_cash(string $from, string $to, string $description): void
    {
        $business = Business::factory()->create();
        $accounts = [
            'tunai' => $business->cashBox(),
            'bca' => tap($business->moneyAccounts()->where('kind', 'non_tunai')->sole())->update(['name' => 'BCA']),
            'gopay' => MoneyAccount::factory()->for($business)->create(['name' => 'GoPay']),
        ];
        $this->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 200_000]);

        $transfer = $this->record($business, ['type' => 'pindah_dana', 'money_account_id' => $accounts[$from]->id, 'to_money_account_id' => $accounts[$to]->id, 'amount' => 50_000]);

        $this->assertSame($description, $transfer->description);
        $this->assertSame(0, $transfer->cash_flow);
        $this->assertSame($accounts[$from]->kind->method(), $transfer->method);
        $balances = app(Ledger::class)->moneyBalances($business);
        $this->assertSame(($from === 'tunai' ? 200_000 : 0) - 50_000, $balances[$accounts[$from]->id]);
        $this->assertSame(($to === 'tunai' ? 200_000 : 0) + 50_000, $balances[$accounts[$to]->id]);

        $this->service()->cancel($transfer);

        $this->assertSame($from === 'tunai' ? 200_000 : 0, app(Ledger::class)->moneyBalances($business)[$accounts[$from]->id]);
    }

    public function test_a_transfer_needs_two_different_active_rekening_of_the_business(): void
    {
        $business = Business::factory()->create();
        $cashBox = $business->cashBox();
        $bca = $business->moneyAccounts()->where('kind', 'non_tunai')->sole();
        $foreign = Business::factory()->create()->cashBox();
        $transfer = fn (int $from, int $to) => $this->record($business, ['type' => 'pindah_dana', 'money_account_id' => $from, 'to_money_account_id' => $to, 'amount' => 1000]);

        $this->assertValidationError('to_money_account_id', 'Rekening asal dan tujuan harus berbeda.', fn () => $transfer($cashBox->id, $cashBox->id));
        $this->assertValidationError('to_money_account_id', 'Rekening tidak ditemukan atau sudah diarsipkan.', fn () => $transfer($cashBox->id, $foreign->id));
        $bca->delete();
        $this->assertValidationError('money_account_id', 'Rekening tidak ditemukan atau sudah diarsipkan.', fn () => $transfer($bca->id, $cashBox->id));
        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_editing_can_move_a_record_to_another_rekening(): void
    {
        $business = Business::factory()->create();
        $bca = $business->moneyAccounts()->where('kind', 'non_tunai')->sole();
        $gopay = MoneyAccount::factory()->for($business)->create(['name' => 'GoPay']);
        $setor = ['type' => 'modal', 'kind' => 'setor', 'method' => 'non_tunai', 'amount' => 80_000];
        $deposit = $this->record($business, $setor + ['money_account_id' => $bca->id]);

        $this->service()->replace($deposit, $setor + ['money_account_id' => $gopay->id]);

        $this->assertSame([$bca->id => 0, $gopay->id => 80_000], app(Ledger::class)->moneyBalances($business));
    }

    private function service(): TransactionService
    {
        return app(TransactionService::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function record(Business $business, array $data): Transaction
    {
        return app(TransactionService::class)->record($business, $data);
    }

    private function stocked(Business $business, string $name, float $qty, int $unitCost, ?int $price = null, bool $material = false): Item
    {
        $factory = $material ? Item::factory()->material() : Item::factory();
        $item = $factory->for($business)->create(['name' => $name, 'price' => $material ? null : $price, 'unit' => $name === 'Telur' ? 'butir' : ($material ? 'kg' : 'botol')]);
        app(Inventory::class)->receive($item, $qty, $unitCost, StockReason::StokAwal, '3101');

        return $item->refresh();
    }

    /**
     * Net amount per account for the transaction's journal: + debit, − credit.
     *
     * @return array<int, int>
     */
    private function journal(Transaction $transaction): array
    {
        $amounts = [];

        foreach ($transaction->journalEntries()->with('lines.account')->get() as $entry) {
            foreach ($entry->lines as $line) {
                $amounts[(int) $line->account->code] = ($amounts[(int) $line->account->code] ?? 0) + $line->debit - $line->credit;
            }
        }

        ksort($amounts);

        return $amounts;
    }

    private function assertValidationError(string $field, string $message, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected a validation error on {$field}.");
        } catch (ValidationException $exception) {
            $this->assertSame([$field => [$message]], $exception->errors());
        }
    }
}
