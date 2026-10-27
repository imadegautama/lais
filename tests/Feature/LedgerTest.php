<?php

namespace Tests\Feature;

use App\Enums\ContactType;
use App\Enums\MoneyAccountKind;
use App\Enums\StockReason;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\MoneyAccount;
use App\Models\Transaction;
use App\Services\Inventory;
use App\Services\Ledger;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_balanced_entry_is_stored_with_its_lines(): void
    {
        $business = Business::factory()->create();

        $cashBox = $business->cashBox();

        app(Ledger::class)->post($business, 'Setor modal', [
            ['account' => '1101', 'money_account_id' => $cashBox->id, 'debit' => 500_000],
            ['account' => '3101', 'credit' => 500_000],
        ]);

        $this->assertSame(500_000, app(Ledger::class)->balance($business, '1101'));
        $this->assertSame(500_000, app(Ledger::class)->balance($business, '3101'));
        $this->assertSame([$cashBox->id => 500_000], app(Ledger::class)->moneyBalances($business));
    }

    public function test_an_unbalanced_entry_is_rejected_and_nothing_is_stored(): void
    {
        $business = Business::factory()->create();

        try {
            app(Ledger::class)->post($business, 'Salah', [
                ['account' => '1101', 'money_account_id' => $business->cashBox()->id, 'debit' => 500_000],
                ['account' => '3101', 'credit' => 400_000],
            ]);
            $this->fail('Unbalanced entry was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, JournalEntry::query()->count());
        }
    }

    public function test_an_unknown_account_rolls_back_the_whole_entry(): void
    {
        $business = Business::factory()->create();

        try {
            app(Ledger::class)->post($business, 'Salah akun', [
                ['account' => '1101', 'money_account_id' => $business->cashBox()->id, 'debit' => 1000],
                ['account' => '9999', 'credit' => 1000],
            ]);
            $this->fail('Unknown account was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, JournalEntry::query()->count());
        }
    }

    public function test_one_entry_may_move_money_between_two_rekening_of_the_same_account(): void
    {
        $business = Business::factory()->create();
        $bca = $business->moneyAccounts()->where('kind', 'non_tunai')->sole();
        $gopay = MoneyAccount::factory()->for($business)->create(['name' => 'GoPay']);

        app(Ledger::class)->post($business, 'Pindah', Ledger::lines([Ledger::cashKey($gopay) => 30_000, Ledger::cashKey($bca) => -30_000]));

        $this->assertSame([$bca->id => -30_000, $gopay->id => 30_000], app(Ledger::class)->moneyBalances($business));
        $this->assertSame(0, app(Ledger::class)->balance($business, '1102'));
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function misplacedMoney(): array
    {
        return [
            'cash without its rekening' => ['1101', null],
            'Kas tunai on the non-tunai account' => ['1102', 'tunai'],
            'a rekening on a non-cash account' => ['3101', 'tunai'],
            'another businesses rekening' => ['1102', 'foreign'],
        ];
    }

    #[DataProvider('misplacedMoney')]
    public function test_money_lines_must_name_a_matching_rekening_of_the_business(string $account, ?string $moneyAccount): void
    {
        $business = Business::factory()->create();
        $id = match ($moneyAccount) {
            'tunai' => $business->cashBox()->id,
            'foreign' => Business::factory()->create()->moneyAccounts()->where('kind', 'non_tunai')->value('id'),
            default => null,
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/rekening/i');

        app(Ledger::class)->post($business, 'Salah rekening', [
            ['account' => $account, 'debit' => 1000] + ($id === null ? [] : ['money_account_id' => $id]),
            ['account' => '3101', 'credit' => 1000],
        ]);
    }

    public function test_random_stock_operations_keep_the_books_balanced(): void
    {
        $business = Business::factory()->create();
        $items = Item::factory()->count(4)->material()->for($business)->create();
        $inventory = app(Inventory::class);
        $ledger = app(Ledger::class);
        $random = new Randomizer(new Mt19937(2026));

        for ($i = 0; $i < 200; $i++) {
            $item = $items[$random->getInt(0, 3)]->refresh();

            if ($random->getInt(0, 2) > 0) {
                $inventory->receive($item, $random->getInt(1, 200) / 10, $random->getInt(1, 200) * 100, StockReason::StokAwal, '3101');
            } else {
                $inventory->adjust($item, $random->getInt(0, 300) / 10);
            }
        }

        $this->assertSame((int) JournalLine::query()->sum('debit'), (int) JournalLine::query()->sum('credit'));
        $this->assertSame((int) $items->fresh()->sum('stock_value'), $ledger->balance($business, '1301'));

        // Aset = Kewajiban + Ekuitas + (Pendapatan − Beban)
        $assets = $ledger->balance($business, '1301');
        $equity = $ledger->balance($business, '3101');
        $expenses = $ledger->balance($business, '6201');
        $this->assertSame($assets, $equity - $expenses);
    }

    public function test_random_transactions_keep_the_books_balanced(): void
    {
        $business = Business::factory()->create();
        $ledger = app(Ledger::class);
        $service = app(TransactionService::class);
        $inventory = app(Inventory::class);
        $random = new Randomizer(new Mt19937(2027));
        $pick = fn (array $options) => $options[$random->getInt(0, count($options) - 1)];

        $materials = Item::factory()->count(3)->material()->for($business)->create();
        $goods = Item::factory()->count(2)->for($business)->create(['price' => 6000]);
        $recipe = Item::factory()->recipe()->for($business)->create(['price' => 15000]);
        $recipe->recipeLines()->createMany([
            ['material_id' => $materials[0]->id, 'qty' => 0.15],
            ['material_id' => $materials[1]->id, 'qty' => 1],
        ]);
        $stocked = [...$materials->all(), ...$goods->all()];
        $sellable = [...$goods->all(), $recipe];
        MoneyAccount::factory()->for($business)->create(['name' => 'GoPay']);
        $moneyAccounts = $business->moneyAccounts()->get();
        $nonCash = $moneyAccounts->where('kind', MoneyAccountKind::NonTunai)->pluck('id')->all();
        // A way to pay; non-tunai picks one of the two rekening, as the form asks when there are several.
        $pay = function (array $allowed) use ($pick, $nonCash): array {
            $method = $pick($allowed);

            return ['method' => $method] + ($method === 'non_tunai' ? ['money_account_id' => $pick($nonCash)] : []);
        };

        // Cancel or edit an earlier record. One with payments against it is refused, and the refusal must leave it untouched.
        $revise = function (bool $edit) use ($service, $business, $random, $pick, $pay) {
            $posted = Transaction::query()->where('business_id', $business->id)->where('status', 'posted')->pluck('id')->all();

            if ($posted === []) {
                return;
            }

            $transaction = Transaction::query()->findOrFail($pick($posted));

            try {
                $edit
                    ? $service->replace($transaction, ['type' => 'modal', 'kind' => 'setor', ...$pay(['tunai', 'non_tunai']), 'amount' => $random->getInt(1, 300) * 1000])
                    : $service->cancel($transaction);
            } catch (ValidationException) {
                $this->assertSame(TransactionStatus::Posted, $transaction->refresh()->status);
            }
        };

        for ($step = 0; $step < 150; $step++) {
            match ($random->getInt(0, 10)) {
                0, 1 => (function () use ($service, $business, $random, $pick, $sellable, $pay) {
                    $lines = collect($random->shuffleArray($sellable))->take($random->getInt(1, 2))
                        ->map(fn (Item $item) => ['item_id' => $item->id, 'qty' => $random->getInt(1, 4), 'price' => $random->getInt(10, 200) * 100])->values()->all();
                    $total = array_sum(array_map(fn ($line) => $line['qty'] * $line['price'], $lines));
                    $payment = $pay(['tunai', 'non_tunai', 'kredit']);
                    $service->record($business, ['type' => 'penjualan', ...$payment, 'lines' => $lines] + ($payment['method'] === 'kredit'
                        ? ['contact_name' => $pick(['Bu Sari', 'Pak Budi']), 'paid_amount' => $random->getInt(0, $total - 1)]
                        : []));
                })(),
                2 => (function () use ($service, $business, $random, $pick, $stocked, $pay) {
                    $lines = collect($random->shuffleArray($stocked))->take($random->getInt(1, 2))
                        ->map(fn (Item $item) => ['item_id' => $item->id, 'qty' => $random->getInt(1, 200) / 10, 'price' => $random->getInt(5, 300) * 100])->values()->all();
                    $service->record($business, ['type' => 'pembelian', 'kind' => 'barang', ...$pay(['tunai', 'non_tunai', 'kredit', 'pribadi']), 'contact_name' => $pick(['Toko A', 'Toko B']), 'lines' => $lines]);
                })(),
                3 => $service->record($business, [
                    'type' => $type = $pick(['biaya', 'pembelian']),
                    'kind' => $type === 'biaya' ? $pick(array_column(TransactionType::Biaya->kinds(), 'value')) : 'peralatan',
                    ...$pay(['tunai', 'non_tunai', 'kredit', 'pribadi']),
                    'contact_name' => $pick(['Toko A', 'Toko B']),
                    'amount' => $random->getInt(1, 500) * 1000,
                    'note' => 'acak',
                ]),
                4 => (function () use ($service, $ledger, $business, $random, $pick, $pay) {
                    $open = array_filter($ledger->contactBalances($business), fn (int $balance) => $balance > 0);

                    if ($open === []) {
                        return;
                    }

                    $contactId = $pick(array_keys($open));
                    $contact = Contact::query()->findOrFail($contactId);
                    $service->record($business, [
                        'type' => 'utang_piutang',
                        'kind' => $contact->type === ContactType::Pelanggan ? 'terima_piutang' : 'bayar_utang',
                        ...$pay(['tunai', 'non_tunai']),
                        'contact_id' => $contactId,
                        'amount' => $random->getInt(1, $open[$contactId]),
                    ]);
                })(),
                5 => $service->record($business, ['type' => 'modal', 'kind' => $pick(['setor', 'prive']), ...$pay(['tunai', 'non_tunai']), 'amount' => $random->getInt(1, 300) * 1000]),
                6 => $inventory->adjust($pick($stocked)->refresh(), $random->getInt(0, 300) / 10),
                7 => $revise(false),
                8 => $revise(true),
                9 => (function () use ($service, $business, $random, $moneyAccounts) {
                    [$from, $to] = $random->shuffleArray($moneyAccounts->all());
                    $service->record($business, ['type' => 'pindah_dana', 'money_account_id' => $from->id, 'to_money_account_id' => $to->id, 'amount' => $random->getInt(1, 200) * 1000]);
                })(),
                10 => $service->record($business, ['type' => 'saldo_awal', 'kind' => $kind = $pick(['saldo_kas', 'saldo_peralatan', 'saldo_utang', 'saldo_piutang']), 'amount' => $random->getInt(1, 500) * 1000] + match ($kind) {
                    'saldo_kas' => ['money_account_id' => $pick($moneyAccounts->all())->id],
                    'saldo_peralatan' => ['note' => 'Etalase'],
                    'saldo_utang' => ['contact_name' => $pick(['Toko A', 'Toko B'])],
                    default => ['contact_name' => $pick(['Bu Sari', 'Pak Budi'])],
                }),
            };

            // Checked after every step: stock at or below zero carries no value, stock above zero never negative value.
            foreach (Item::query()->where('business_id', $business->id)->where('kind', '<>', 'komposisi')->get() as $item) {
                $valid = (float) $item->stock > 0 ? $item->stock_value >= 0 : $item->stock_value === 0;
                $this->assertTrue($valid, "Step {$step}: {$item->name} has stock {$item->stock} at value {$item->stock_value}");
            }
        }

        $balance = fn (string $code) => $ledger->balance($business, $code);
        $items = Item::query()->withTrashed()->where('business_id', $business->id)->get();
        $contacts = Contact::query()->get()->keyBy('id');
        $contactBalances = collect($ledger->contactBalances($business));

        $this->assertSame((int) JournalLine::query()->sum('debit'), (int) JournalLine::query()->sum('credit'));
        $this->assertSame($items->sum('stock_value'), $balance('1301'));
        // Aset = Kewajiban + Modal − Prive + Pendapatan − Beban
        $this->assertSame(
            $balance('1101') + $balance('1102') + $balance('1201') + $balance('1301') + $balance('1401'),
            $balance('2101') + $balance('2201') + $balance('3101') - $balance('3201') + $balance('4101') - $balance('5101') - $balance('6101') - $balance('6201'),
        );
        $this->assertSame($balance('1201'), $contactBalances->filter(fn ($amount, $id) => $contacts[$id]->type === ContactType::Pelanggan)->sum());
        $this->assertSame($balance('2101'), $contactBalances->filter(fn ($amount, $id) => $contacts[$id]->type === ContactType::Supplier)->sum());
        $this->assertSame($balance('1101') + $balance('1102'), (int) Transaction::query()->where('status', 'posted')->sum('cash_flow'));
        // Each rekening is read from the same journal, so they add up to 1101 and 1102.
        $byKind = fn (MoneyAccountKind $kind) => collect($ledger->moneyBalances($business))->only($moneyAccounts->where('kind', $kind)->pluck('id')->all())->sum();
        $this->assertSame($balance('1101'), $byKind(MoneyAccountKind::Tunai));
        $this->assertSame($balance('1102'), $byKind(MoneyAccountKind::NonTunai));
        $this->assertSame(0, JournalLine::query()->whereNull('money_account_id')->whereHas('account', fn ($query) => $query->whereIn('code', ['1101', '1102']))->count());
        $this->assertGreaterThan(5, Transaction::query()->where('type', 'pindah_dana')->count());
        $this->assertGreaterThan(5, Transaction::query()->where('type', 'saldo_awal')->count());

        $this->assertGreaterThan(100, Transaction::query()->count());
        $this->assertGreaterThan(5, Transaction::query()->where('status', 'void')->whereNull('replaced_by_id')->count());
        $this->assertGreaterThan(5, Transaction::query()->whereNotNull('replaced_by_id')->count());
    }
}
