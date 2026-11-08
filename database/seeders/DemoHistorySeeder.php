<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Models\Business;
use App\Models\Item;
use App\Services\Depreciation;
use App\Services\Ledger;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Three months of everyday records for "Warung Bu Rina": what the landing
 * page's screenshots show and what judges open with the demo account. The
 * dice are seeded, so every run writes the same books, and everything goes
 * through TransactionService, so the numbers are what the app computes.
 *
 * It refuses a business that already has records, so it can never add to
 * real books.
 */
class DemoHistorySeeder extends Seeder
{
    public const DAYS = 90;

    /** What customers order, out of 100. */
    private const MENU = ['Nasi Goreng' => 35, 'Mie Goreng' => 25, 'Es Teh' => 25, 'Air Mineral 600 ml' => 10, 'Kerupuk' => 5];

    /** What the warung tops its shelf up to, enough for four busy days. */
    private const SHELF = [
        'Beras' => 12, 'Telur' => 130, 'Minyak goreng' => 3, 'Bumbu & kecap' => 130, 'Mie telur' => 55,
        'Teh celup' => 55, 'Gula pasir' => 2, 'Air Mineral 600 ml' => 24, 'Kerupuk' => 90,
    ];

    public int $days = self::DAYS;

    private Randomizer $dice;

    private Business $business;

    /** @var Collection<string, Item> */
    private Collection $items;

    public function __construct(private TransactionService $transactions, private Ledger $ledger, private Depreciation $depreciation) {}

    public function run(): void
    {
        $business = Business::query()->where('businesses.slug', 'warung-bu-rina')->first();

        if ($business === null || $business->transactions()->where('transactions.type', '!=', TransactionType::SaldoAwal)->exists()) {
            return;
        }

        $this->business = $business;
        $this->dice = new Randomizer(new Mt19937(2026));
        $this->items = $business->items()->get()->keyBy('name');

        for ($ago = $this->days; $ago >= 0; $ago--) {
            $day = CarbonImmutable::today()->subDays($ago);

            if ($ago % 3 === 0) {
                $this->restock($day->setTime(7, 30));
            }

            $this->expenses($day);
            $this->sales($day, 1 - $ago / self::DAYS);
            $this->occasions($ago, $day);
        }

        $this->depreciation->run(business: $business);
    }

    /**
     * A day of sales. Business grows over the three months and weekends are busier.
     */
    private function sales(CarbonImmutable $day, float $growth): void
    {
        $count = 8 + (int) round(6 * $growth) + ($day->isWeekend() ? 3 : 0) + $this->dice->getInt(-1, 2);

        for ($sale = 0; $sale < $count; $sale++) {
            $at = $day->setTime($this->dice->getInt(10, 20), $this->dice->getInt(0, 59));

            if ($at->isPast()) {
                $this->record(['type' => 'penjualan', 'occurred_at' => $at->format('Y-m-d H:i'), 'lines' => $this->order(), ...$this->payment()]);
            }
        }
    }

    /**
     * One customer's order.
     *
     * @return list<array{item_id: int, qty: int, price: int|null}>
     */
    private function order(): array
    {
        $picked = [];

        for ($line = $this->dice->getInt(1, 2); $line > 0; $line--) {
            $picked[$this->pick()] = $this->dice->getInt(1, 3);
        }

        return array_map(fn (string $name, int $qty) => $this->line($name, $qty), array_keys($picked), $picked);
    }

    private function pick(): string
    {
        $roll = $this->dice->getInt(1, 100);

        foreach (self::MENU as $name => $share) {
            $roll -= $share;

            if ($roll <= 0) {
                return $name;
            }
        }

        return array_key_first(self::MENU);
    }

    /**
     * How a customer pays: mostly cash, then QRIS to GoPay, sometimes a transfer.
     *
     * @return array<string, int|string>
     */
    private function payment(): array
    {
        $roll = $this->dice->getInt(1, 10);

        return match (true) {
            $roll <= 6 => ['method' => 'tunai'],
            $roll <= 9 => ['method' => 'non_tunai', 'money_account_id' => $this->account('GoPay')],
            default => ['method' => 'non_tunai', 'money_account_id' => $this->account('BCA')],
        };
    }

    private function restock(CarbonImmutable $at): void
    {
        $lines = [];

        foreach (self::SHELF as $name => $target) {
            $item = $this->items[$name]->refresh();
            $need = round($target - (float) $item->stock, 1);

            if ($need > 0) {
                $lines[] = ['item_id' => $item->id, 'qty' => $need, 'price' => (int) round($item->unitCost())];
            }
        }

        if ($lines !== []) {
            $this->record(['type' => 'pembelian', 'kind' => 'barang', 'contact_name' => 'Toko Makmur', 'occurred_at' => $at->format('Y-m-d H:i'), 'lines' => $lines, ...$this->fullestAccount()]);
        }
    }

    private function expenses(CarbonImmutable $day): void
    {
        $bills = match (true) {
            $day->day === 25 => ['sewa' => 1_500_000],
            $day->day === 20 => ['listrik_air' => 350_000],
            $day->isSaturday() => ['upah' => 400_000, 'gas_bbm' => 180_000],
            default => [],
        };

        foreach ($bills as $kind => $amount) {
            $this->record(['type' => 'biaya', 'kind' => $kind, 'amount' => $amount, 'occurred_at' => $day->setTime(8, 0)->format('Y-m-d H:i'), ...$this->fullestAccount()]);
        }

        if ($day->isSunday()) {
            $this->record(['type' => 'modal', 'kind' => 'prive', 'amount' => 50_000 * $this->dice->getInt(4, 6), 'occurred_at' => $day->setTime(21, 0)->format('Y-m-d H:i'), ...$this->fullestAccount()]);
        }
    }

    /**
     * The few records that do not repeat: new equipment, regulars paying
     * later, and one bill still owed to the supplier.
     */
    private function occasions(int $ago, CarbonImmutable $day): void
    {
        $at = $day->setTime(21, 30)->format('Y-m-d H:i');

        match ($ago) {
            80 => $this->record(['type' => 'pembelian', 'kind' => 'peralatan', 'amount' => 1_200_000, 'note' => 'Kompor dua tungku', 'useful_life_months' => 48, 'occurred_at' => $at, ...$this->fullestAccount()]),
            40 => $this->onCredit('Bu Sari', 2, $at),
            30 => $this->record(['type' => 'utang_piutang', 'kind' => 'terima_piutang', 'method' => 'tunai', 'contact_id' => $this->contact('Bu Sari'), 'amount' => 30_000, 'occurred_at' => $at]),
            21 => $this->onCredit('Bu Sari', 3, $at),
            14 => $this->onCredit('Pak Budi', 2, $at),
            10 => $this->record(['type' => 'pembelian', 'kind' => 'barang', 'method' => 'kredit', 'contact_name' => 'Toko Makmur', 'occurred_at' => $at, 'lines' => [$this->line('Beras', 10, 14_000)]]),
            default => null,
        };
    }

    private function onCredit(string $customer, int $portions, string $at): void
    {
        $this->record(['type' => 'penjualan', 'method' => 'kredit', 'contact_name' => $customer, 'occurred_at' => $at, 'lines' => [$this->line('Nasi Goreng', $portions)]]);
    }

    /**
     * @return array{item_id: int, qty: int, price: int|null}
     */
    private function line(string $name, int $qty, ?int $price = null): array
    {
        $item = $this->items[$name];

        return ['item_id' => $item->id, 'qty' => $qty, 'price' => $price ?? $item->price];
    }

    /**
     * Pays from wherever holds the most, so no account dips below zero.
     *
     * @return array<string, int|string>
     */
    private function fullestAccount(): array
    {
        $balances = $this->ledger->moneyBalances($this->business);
        arsort($balances);
        $account = $this->business->moneyAccounts()->findOrFail(array_key_first($balances));

        return $account->name === 'Kas tunai'
            ? ['method' => 'tunai']
            : ['method' => 'non_tunai', 'money_account_id' => $account->id];
    }

    private function account(string $name): int
    {
        return $this->business->moneyAccounts()->where('money_accounts.name', $name)->value('money_accounts.id');
    }

    private function contact(string $name): int
    {
        return $this->business->contacts()->where('contacts.name', $name)->value('contacts.id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function record(array $data): void
    {
        $this->transactions->record($this->business, $data);
    }
}
