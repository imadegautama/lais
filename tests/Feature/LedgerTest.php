<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\Inventory;
use App\Services\Ledger;
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
                $inventory->receive($item, $random->getInt(1, 200) / 10, $random->getInt(1, 200) * 100, 'stok_awal', '3101');
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
}
