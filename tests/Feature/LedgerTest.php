<?php

namespace Tests\Feature;

use App\Enums\ContactType;
use App\Enums\StockReason;
use App\Enums\TransactionType;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Transaction;
use App\Services\Inventory;
use App\Services\Ledger;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_balanced_entry_is_stored_with_its_lines(): void
    {
        $business = Business::factory()->create();

        app(Ledger::class)->post($business, 'Setor modal', [
            ['account' => '1101', 'debit' => 500_000],
            ['account' => '3101', 'credit' => 500_000],
        ]);

        $this->assertSame(500_000, app(Ledger::class)->balance($business, '1101'));
        $this->assertSame(500_000, app(Ledger::class)->balance($business, '3101'));
    }

    public function test_an_unbalanced_entry_is_rejected_and_nothing_is_stored(): void
    {
        $business = Business::factory()->create();

        try {
            app(Ledger::class)->post($business, 'Salah', [
                ['account' => '1101', 'debit' => 500_000],
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
                ['account' => '1101', 'debit' => 1000],
                ['account' => '9999', 'credit' => 1000],
            ]);
            $this->fail('Unknown account was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, JournalEntry::query()->count());
        }
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

        for ($step = 0; $step < 150; $step++) {
            $method = fn (array $allowed) => $pick($allowed);

            match ($random->getInt(0, 6)) {
                0, 1 => (function () use ($service, $business, $random, $pick, $sellable, $method) {
                    $lines = collect($random->shuffleArray($sellable))->take($random->getInt(1, 2))
                        ->map(fn (Item $item) => ['item_id' => $item->id, 'qty' => $random->getInt(1, 4), 'price' => $random->getInt(10, 200) * 100])->values()->all();
                    $total = array_sum(array_map(fn ($line) => $line['qty'] * $line['price'], $lines));
                    $chosen = $method(['tunai', 'non_tunai', 'kredit']);
                    $service->record($business, ['type' => 'penjualan', 'method' => $chosen, 'lines' => $lines] + ($chosen === 'kredit'
                        ? ['contact_name' => $pick(['Bu Sari', 'Pak Budi']), 'paid_amount' => $random->getInt(0, $total - 1)]
                        : []));
                })(),
                2 => (function () use ($service, $business, $random, $pick, $stocked, $method) {
                    $lines = collect($random->shuffleArray($stocked))->take($random->getInt(1, 2))
                        ->map(fn (Item $item) => ['item_id' => $item->id, 'qty' => $random->getInt(1, 200) / 10, 'price' => $random->getInt(5, 300) * 100])->values()->all();
                    $service->record($business, ['type' => 'pembelian', 'kind' => 'barang', 'method' => $method(['tunai', 'non_tunai', 'kredit', 'pribadi']), 'contact_name' => $pick(['Toko A', 'Toko B']), 'lines' => $lines]);
                })(),
                3 => $service->record($business, [
                    'type' => $type = $pick(['biaya', 'pembelian']),
                    'kind' => $type === 'biaya' ? $pick(array_column(TransactionType::Biaya->kinds(), 'value')) : 'peralatan',
                    'method' => $method(['tunai', 'non_tunai', 'kredit', 'pribadi']),
                    'contact_name' => $pick(['Toko A', 'Toko B']),
                    'amount' => $random->getInt(1, 500) * 1000,
                    'note' => 'acak',
                ]),
                4 => (function () use ($service, $ledger, $business, $random, $pick) {
                    $open = array_filter($ledger->contactBalances($business), fn (int $balance) => $balance > 0);

                    if ($open === []) {
                        return;
                    }

                    $contactId = $pick(array_keys($open));
                    $contact = Contact::query()->findOrFail($contactId);
                    $service->record($business, [
                        'type' => 'utang_piutang',
                        'kind' => $contact->type === ContactType::Pelanggan ? 'terima_piutang' : 'bayar_utang',
                        'method' => $pick(['tunai', 'non_tunai']),
                        'contact_id' => $contactId,
                        'amount' => $random->getInt(1, $open[$contactId]),
                    ]);
                })(),
                5 => $service->record($business, ['type' => 'modal', 'kind' => $pick(['setor', 'prive']), 'method' => $pick(['tunai', 'non_tunai']), 'amount' => $random->getInt(1, 300) * 1000]),
                6 => $inventory->adjust($pick($stocked)->refresh(), $random->getInt(0, 300) / 10),
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

        $this->assertGreaterThan(100, Transaction::query()->count());
    }
}
