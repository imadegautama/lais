<?php

namespace App\Services;

use App\Enums\ContactType;
use App\Models\Business;
use App\Models\Item;
use App\Models\MoneyAccount;

/**
 * The figures behind Catatan atas Laporan Keuangan, the third report SAK EMKM
 * asks for (par. 3.9, 6.2). Each detail adds up to its line on the laporan
 * posisi keuangan today; the wording of the policies lives with the page.
 */
class FinancialNotes
{
    public function __construct(private Ledger $ledger, private Debts $debts, private Depreciation $depreciation) {}

    /**
     * @return array{money: list<array{name: string, amount: int}>, inventory: list<array{name: string, qty: string, unit: string, amount: int}>, equipment: list<array{id: int, name: string, cost: int, acquiredOn: string, lifeMonths: int|null, accumulated: int, bookValue: int, locked: bool}>, receivables: list<array{name: string, amount: int}>, payables: list<array{name: string, amount: int}>}
     */
    public function details(Business $business): array
    {
        $balances = $this->ledger->moneyBalances($business);
        $open = fn (ContactType $type) => array_map(
            fn (array $row) => ['name' => $row['contact']->name, 'amount' => $row['balance']],
            $this->debts->summary($business, $type),
        );

        return [
            'money' => array_values($business->moneyAccounts()->orderBy('money_accounts.id')->get()
                ->map(fn (MoneyAccount $account) => ['name' => $account->name, 'amount' => $balances[$account->id] ?? 0])
                ->all()),
            'inventory' => array_values($business->items()->where('items.stock_value', '!=', 0)->orderBy('items.name')->get()
                ->map(fn (Item $item) => ['name' => $item->name, 'qty' => $item->stock, 'unit' => $item->unit, 'amount' => $item->stock_value])
                ->all()),
            'equipment' => $this->depreciation->book($business),
            'receivables' => $open(ContactType::Pelanggan),
            'payables' => $open(ContactType::Supplier),
        ];
    }
}
