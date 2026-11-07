<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\ItemKind;
use App\Enums\StockReason;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Business;
use App\Models\Item;
use App\Models\Transaction;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * The numbers behind the dashboard, the reports (PRD M6) and the AI's read
 * tools (§3.1), all read from the journal so they always agree. A cancelled
 * record is mirrored on its own date, so every period nets it to zero.
 */
class Reports
{
    /**
     * Laba vs kas steps: the accounts between profit and cash, keyed for the
     * page's plain-language labels.
     */
    private const CASH_STEPS = [
        'inventory' => Account::INVENTORY,
        'receivables' => Account::RECEIVABLE,
        'equipment' => Account::EQUIPMENT,
        'depreciation' => Account::ACCUMULATED_DEPRECIATION,
        'payables' => Account::PAYABLE,
        'customerDeposits' => Account::CUSTOMER_DEPOSITS,
        'deposits' => Account::OWNER_CAPITAL,
        'drawings' => Account::DRAWINGS,
    ];

    /**
     * Net amount per account in a time range: + debit, − credit. $from is
     * inclusive, $before exclusive.
     *
     * @return array<int, int> account code => rupiah
     */
    public function net(Business $business, ?CarbonInterface $from = null, ?CarbonInterface $before = null): array
    {
        return $this->journal($business, $from, $before)
            ->groupBy('accounts.code')
            // Debit and credit are unsigned, so each side is summed before subtracting (MySQL rejects a negative unsigned).
            ->selectRaw('accounts.code, sum(journal_lines.debit) as debit, sum(journal_lines.credit) as credit')
            ->get()
            ->mapWithKeys(fn (object $row) => [(int) $row->code => (int) $row->debit - (int) $row->credit])
            ->all();
    }

    /**
     * Laba rugi: sales − HPP = gross profit, then other income, operating
     * costs, stock differences, and the accounts the business added, down to
     * net profit. Reading by code range means an added account cannot be missed.
     *
     * @return array{sales: int, cogs: int, grossProfit: int, otherIncome: int, expenses: int, depreciation: int, otherExpenses: int, stockDifference: int, netProfit: int}
     */
    public function profitAndLoss(Business $business, CarbonInterface $from, ?CarbonInterface $before = null): array
    {
        $net = $this->net($business, $from, $before);
        $sales = -($net[Account::SALES] ?? 0);
        $cogs = $net[Account::COGS] ?? 0;
        $expenses = $net[Account::EXPENSES] ?? 0;
        $stockDifference = $net[Account::STOCK_DIFFERENCE] ?? 0;
        $depreciation = $net[Account::DEPRECIATION] ?? 0;
        $income = array_filter($net, fn (int $code) => $code >= 4000 && $code < 5000, ARRAY_FILTER_USE_KEY);
        $costs = array_filter($net, fn (int $code) => $code >= 6000, ARRAY_FILTER_USE_KEY);

        return [
            'sales' => $sales,
            'cogs' => $cogs,
            'grossProfit' => $sales - $cogs,
            'otherIncome' => -array_sum($income) - $sales,
            'expenses' => $expenses,
            'depreciation' => $depreciation,
            'otherExpenses' => array_sum($costs) - $expenses - $stockDifference - $depreciation,
            'stockDifference' => $stockDifference,
            'netProfit' => self::profit($net),
        ];
    }

    /**
     * The accounts a business added that moved in a time range, for the lines
     * of the laba rugi.
     *
     * @return list<array{code: string, name: string, type: AccountType, amount: int}>
     */
    public function ownAccounts(Business $business, CarbonInterface $from, ?CarbonInterface $before = null): array
    {
        $net = $this->net($business, $from, $before);

        return array_values(Account::query()->where('accounts.business_id', $business->id)->orderBy('accounts.code')->get()
            ->filter(fn (Account $account) => ($net[(int) $account->code] ?? 0) !== 0)
            ->map(fn (Account $account) => [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'amount' => abs($net[(int) $account->code]),
            ])
            ->all());
    }

    /**
     * The chart of accounts with where each stands at the end of $date (now
     * when null). Debit and credit are the trial balance's two columns;
     * `balance` is on the account's own side, so a normal balance is positive.
     *
     * @return list<array{id: int, code: string, name: string, type: AccountType, system: bool, archived: bool, debit: int, credit: int, balance: int}>
     */
    public function chart(Business $business, ?CarbonInterface $date = null): array
    {
        $net = $this->net($business, before: $date === null ? null : CarbonImmutable::instance($date)->addDay()->startOfDay());

        return array_values(Account::query()->visibleTo($business)->orderBy('accounts.code')->get()
            ->map(function (Account $account) use ($net) {
                $amount = $net[(int) $account->code] ?? 0;

                return [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'system' => $account->isSystem(),
                    'archived' => $account->archived_at !== null,
                    'debit' => max($amount, 0),
                    'credit' => max(-$amount, 0),
                    'balance' => $account->normal_balance === 'credit' ? -$amount : $amount,
                ];
            })
            ->all());
    }

    /**
     * Buku besar of one account: what it held when the period began, then
     * each line with the running balance on the account's own side.
     *
     * @return array{opening: int, closing: int, lines: list<array{occurredAt: string, description: string, debit: int, credit: int, balance: int}>}
     */
    public function ledger(Business $business, Account $account, CarbonInterface $from, ?CarbonInterface $before = null): array
    {
        $sign = $account->normal_balance === 'credit' ? -1 : 1;
        $balance = $sign * ($this->net($business, before: $from)[(int) $account->code] ?? 0);
        $opening = $balance;
        $lines = [];

        $rows = $this->journal($business, $from, $before)
            ->where('accounts.id', $account->id)
            ->orderBy('journal_entries.occurred_at')
            ->orderBy('journal_lines.id')
            ->select('journal_entries.occurred_at', 'journal_entries.description', 'journal_lines.debit', 'journal_lines.credit')
            ->get();

        foreach ($rows as $row) {
            $balance += $sign * ((int) $row->debit - (int) $row->credit);
            $lines[] = [
                'occurredAt' => CarbonImmutable::parse((string) $row->occurred_at)->toIso8601String(),
                'description' => (string) $row->description,
                'debit' => (int) $row->debit,
                'credit' => (int) $row->credit,
                'balance' => $balance,
            ];
        }

        return ['opening' => $opening, 'closing' => $balance, 'lines' => $lines];
    }

    /**
     * Buku kas: money in and out of one rekening, or all of them, with the
     * running balance. A transfer between two rekening shows on both.
     *
     * @return array{opening: int, closing: int, totalIn: int, totalOut: int, lines: list<array{occurredAt: string, description: string, account: string, in: int, out: int, balance: int}>}
     */
    public function cashBook(Business $business, CarbonInterface $from, ?CarbonInterface $before = null, ?int $moneyAccountId = null): array
    {
        $cash = fn (Builder $query) => $query
            ->whereNotNull('journal_lines.money_account_id')
            ->when($moneyAccountId, fn (Builder $query, int $id) => $query->where('journal_lines.money_account_id', $id));
        $row = $cash($this->journal($business, null, $from))
            ->selectRaw('sum(journal_lines.debit) as debit, sum(journal_lines.credit) as credit')
            ->first();
        $opening = (int) ($row->debit ?? 0) - (int) ($row->credit ?? 0);
        $balance = $opening;
        $lines = [];

        $rows = $cash($this->journal($business, $from, $before))
            ->join('money_accounts', 'money_accounts.id', '=', 'journal_lines.money_account_id')
            ->orderBy('journal_entries.occurred_at')
            ->orderBy('journal_lines.id')
            ->select('journal_entries.occurred_at', 'journal_entries.description', 'money_accounts.name', 'journal_lines.debit', 'journal_lines.credit')
            ->get();

        foreach ($rows as $line) {
            $balance += (int) $line->debit - (int) $line->credit;
            $lines[] = [
                'occurredAt' => CarbonImmutable::parse((string) $line->occurred_at)->toIso8601String(),
                'description' => (string) $line->description,
                'account' => (string) $line->name,
                'in' => (int) $line->debit,
                'out' => (int) $line->credit,
                'balance' => $balance,
            ];
        }

        return [
            'opening' => $opening,
            'closing' => $balance,
            'totalIn' => (int) array_sum(array_column($lines, 'in')),
            'totalOut' => (int) array_sum(array_column($lines, 'out')),
            'lines' => $lines,
        ];
    }

    /**
     * Each product sold in a time range: how much, for how much, and what it
     * earned, from the cost kept on every sale line. Most profitable first.
     *
     * @return list<array{id: int, name: string, unit: string, qty: float, sales: int, cogs: int, profit: int, margin: float|null}>
     */
    public function productPerformance(Business $business, CarbonInterface $from, ?CarbonInterface $before = null): array
    {
        return array_values(DB::table('transaction_lines')
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            ->join('items', 'items.id', '=', 'transaction_lines.item_id')
            ->where('transactions.business_id', $business->id)
            ->where('transactions.type', TransactionType::Penjualan)
            ->where('transactions.status', TransactionStatus::Posted)
            ->where('transactions.occurred_at', '>=', $from)
            ->when($before, fn (Builder $query) => $query->where('transactions.occurred_at', '<', $before))
            ->groupBy('items.id', 'items.name', 'items.unit')
            ->selectRaw('items.id, items.name, items.unit, sum(transaction_lines.qty) as qty, sum(transaction_lines.subtotal) as sales, sum(transaction_lines.hpp) as cogs')
            ->get()
            ->map(function (object $row) {
                $sales = (int) $row->sales;
                $profit = $sales - (int) $row->cogs;

                return [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'unit' => (string) $row->unit,
                    'qty' => (float) $row->qty,
                    'sales' => $sales,
                    'cogs' => (int) $row->cogs,
                    'profit' => $profit,
                    'margin' => $sales > 0 ? round($profit / $sales * 100, 1) : null,
                ];
            })
            ->sortByDesc('profit')
            ->all());
    }

    /**
     * Products with stock on the shelf that have not sold for $days days:
     * money sitting still, oldest sale first.
     *
     * @return list<array{id: int, name: string, stock: float, unit: string, value: int, lastSoldAt: string|null}>
     */
    public function slowStock(Business $business, int $days = 30): array
    {
        $lastSold = DB::table('transaction_lines')
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            ->where('transactions.business_id', $business->id)
            ->where('transactions.type', TransactionType::Penjualan)
            ->where('transactions.status', TransactionStatus::Posted)
            ->groupBy('transaction_lines.item_id')
            ->selectRaw('transaction_lines.item_id, max(transactions.occurred_at) as sold_at')
            ->pluck('sold_at', 'item_id');
        $since = now()->subDays($days);

        return array_values($business->items()
            ->where('items.kind', ItemKind::Produk)
            ->where('items.stock', '>', 0)
            ->orderBy('items.name')
            ->get()
            ->filter(fn (Item $item) => ! isset($lastSold[$item->id]) || CarbonImmutable::parse((string) $lastSold[$item->id]) < $since)
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'stock' => (float) $item->stock,
                'unit' => $item->unit,
                'value' => $item->stock_value,
                'lastSoldAt' => isset($lastSold[$item->id]) ? CarbonImmutable::parse((string) $lastSold[$item->id])->toIso8601String() : null,
            ])
            ->sortBy(fn (array $row) => $row['lastSoldAt'] ?? '')
            ->all());
    }

    /**
     * Sales and profit of the last $months months, oldest first, this month
     * so far included. Each is the same reading as the laba rugi of that month.
     *
     * @return list<array{month: string, label: string, sales: int, netProfit: int}>
     */
    public function monthlyTrend(Business $business, int $months = 6): array
    {
        $current = now()->toImmutable()->startOfMonth();
        $trend = [];

        for ($back = $months - 1; $back >= 0; $back--) {
            $from = $current->subMonthsNoOverflow($back);
            $report = $this->profitAndLoss($business, $from, $from->addMonthNoOverflow());
            $trend[] = [
                'month' => $from->format('Y-m'),
                'label' => $from->translatedFormat('M'),
                'sales' => $report['sales'],
                'netProfit' => $report['netProfit'],
            ];
        }

        return $trend;
    }

    /**
     * Posted sales records in a time range.
     */
    public function salesCount(Business $business, CarbonInterface $from, ?CarbonInterface $before = null): int
    {
        return $business->transactions()
            ->where('transactions.status', TransactionStatus::Posted)
            ->where('transactions.type', TransactionType::Penjualan)
            ->where('transactions.occurred_at', '>=', $from)
            ->when($before, fn ($query) => $query->where('transactions.occurred_at', '<', $before))
            ->count();
    }

    /**
     * Taken for personal use (Prive) in a time range.
     */
    public function drawings(Business $business, CarbonInterface $from, ?CarbonInterface $before = null): int
    {
        return $this->net($business, $from, $before)[Account::DRAWINGS] ?? 0;
    }

    /**
     * Owner's equity before $before (default: now): assets − liabilities,
     * which includes the profit not yet closed.
     */
    public function equityAt(Business $business, ?CarbonInterface $before = null): int
    {
        return self::assetsLessLiabilities($this->net($business, before: $before));
    }

    /**
     * Modal awal of a period: equity just before it. In the period the books
     * open, that is the opening balance sheet, which the wizard dates at
     * started_on 00:00 (saldo awal and opening stock alike).
     */
    public function openingEquity(Business $business, CarbonInterface $from, ?CarbonInterface $before = null): int
    {
        return $this->equityAt($business, $this->periodStart($business, $from, $before));
    }

    /**
     * Biaya operasional per jenis (listrik & air, upah, …): the 6101 lines
     * grouped by the kind of the record that posted them. A cancellation is
     * mirrored with the same source, so it nets out here as well.
     *
     * @return array<string, int> TransactionKind value => rupiah, without zeros
     */
    public function expensesByKind(Business $business, CarbonInterface $from, ?CarbonInterface $before = null): array
    {
        return $this->journal($business, $from, $before)
            ->join('transactions', fn (JoinClause $join) => $join->on('transactions.id', '=', 'journal_entries.source_id')
                ->where('journal_entries.source_type', (new Transaction)->getMorphClass()))
            ->where('accounts.code', Account::EXPENSES)
            ->groupBy('transactions.kind')
            ->selectRaw('transactions.kind, sum(journal_lines.debit) as debit, sum(journal_lines.credit) as credit')
            ->get()
            ->mapWithKeys(fn (object $row) => [(string) $row->kind => (int) $row->debit - (int) $row->credit])
            ->filter(fn (int $amount) => $amount !== 0)
            ->all();
    }

    /**
     * Perubahan modal (PRD M6): modal awal + setoran + laba bersih − prive =
     * modal akhir. Setoran is every credit to Modal Pemilik after the
     * opening: setor modal, spending paid with personal money, and the
     * opening stock of products added later.
     *
     * @return array{opening: int, deposits: int, netProfit: int, drawings: int, closing: int}
     */
    public function equityChanges(Business $business, CarbonInterface $from, ?CarbonInterface $before = null): array
    {
        $start = $this->periodStart($business, $from, $before);
        $net = $this->net($business, $start, $before);

        return [
            'opening' => $this->equityAt($business, $start),
            'deposits' => -($net[Account::OWNER_CAPITAL] ?? 0),
            'netProfit' => self::profit($net),
            'drawings' => $net[Account::DRAWINGS] ?? 0,
            'closing' => $this->equityAt($business, $before),
        ];
    }

    /**
     * Neraca at the end of $date (PRD M6): what the business owns, what it
     * owes, and the owner's part. It balances by construction (§4.4);
     * `balanced` is the proof shown on screen.
     *
     * @return array{assets: array{cash: int, nonCash: int, receivables: int, inventory: int, equipment: int, accumulatedDepreciation: int, total: int}, liabilities: array{payables: int, customerDeposits: int, total: int}, equity: array{capital: int, drawings: int, profit: int, total: int}, balanced: bool}
     */
    public function balanceSheet(Business $business, CarbonInterface $date): array
    {
        $net = $this->net($business, before: CarbonImmutable::instance($date)->addDay()->startOfDay());
        $at = fn (string $code) => $net[(int) $code] ?? 0;

        $assets = ['cash' => $at(Account::CASH), 'nonCash' => $at(Account::NON_CASH), 'receivables' => $at(Account::RECEIVABLE), 'inventory' => $at(Account::INVENTORY), 'equipment' => $at(Account::EQUIPMENT), 'accumulatedDepreciation' => $at(Account::ACCUMULATED_DEPRECIATION)];
        $liabilities = ['payables' => -$at(Account::PAYABLE), 'customerDeposits' => -$at(Account::CUSTOMER_DEPOSITS)];
        // Prive is shown as what it takes away from the owner's part.
        $equity = ['capital' => -$at(Account::OWNER_CAPITAL), 'drawings' => -$at(Account::DRAWINGS), 'profit' => self::profit($net)];

        return [
            'assets' => [...$assets, 'total' => array_sum($assets)],
            'liabilities' => [...$liabilities, 'total' => array_sum($liabilities)],
            'equity' => [...$equity, 'total' => array_sum($equity)],
            'balanced' => array_sum($assets) === array_sum($liabilities) + array_sum($equity),
        ];
    }

    /**
     * Laba vs kas (PRD §3.1 rekonsiliasi_laba_kas): why cash moved by more or
     * less than the profit. Each step is its effect on cash (the account's
     * net with the sign flipped), so laba + steps = perubahan kas and the
     * difference is always Rp0 (K3).
     *
     * @return array{netProfit: int, steps: list<array{key: string, amount: int}>, cashChange: int, difference: int}
     */
    public function cashReconciliation(Business $business, CarbonInterface $from, ?CarbonInterface $before = null): array
    {
        $net = $this->net($business, $this->periodStart($business, $from, $before), $before);
        $profit = self::profit($net);
        $steps = [];

        foreach (self::CASH_STEPS as $key => $code) {
            $amount = -($net[(int) $code] ?? 0);

            if ($amount !== 0) {
                $steps[] = ['key' => $key, 'amount' => $amount];
            }
        }

        $cashChange = ($net[(int) Account::CASH] ?? 0) + ($net[(int) Account::NON_CASH] ?? 0);

        return [
            'netProfit' => $profit,
            'steps' => $steps,
            'cashChange' => $cashChange,
            'difference' => $profit + array_sum(array_column($steps, 'amount')) - $cashChange,
        ];
    }

    /**
     * Cek kesehatan usaha (PRD M8), for this month.
     *
     * @return list<array{key: string, label: string, passed: bool, detail: string}>
     */
    public function healthChecks(Business $business): array
    {
        $monthStart = now()->startOfMonth();
        $profit = $this->profitAndLoss($business, $monthStart)['netProfit'];
        $drawn = $this->drawings($business, $monthStart);
        $net = $this->net($business);
        $cash = ($net[Account::CASH] ?? 0) + ($net[Account::NON_CASH] ?? 0);
        $debts = -(($net[Account::PAYABLE] ?? 0) + ($net[Account::CUSTOMER_DEPOSITS] ?? 0));
        $equity = self::assetsLessLiabilities($net);
        $outflow = $this->monthlyOutflow($business);

        return [
            ['key' => 'laba', 'label' => 'Usaha untung bulan ini', 'passed' => $profit > 0, 'detail' => 'Laba bersih '.Format::rupiah($profit)],
            ['key' => 'kas', 'label' => 'Kas cukup untuk sebulan', 'passed' => $cash >= $outflow, 'detail' => 'Kas '.Format::rupiah($cash).', pengeluaran rata-rata sebulan '.Format::rupiah($outflow)],
            ['key' => 'utang', 'label' => 'Utang di bawah modal', 'passed' => $debts <= $equity, 'detail' => 'Utang '.Format::rupiah($debts).', modal '.Format::rupiah($equity)],
            // Taking nothing never eats into capital, even in a month at a loss.
            ['key' => 'prive', 'label' => 'Ambil pribadi tidak melebihi laba', 'passed' => $drawn <= max(0, $profit), 'detail' => 'Diambil '.Format::rupiah($drawn).' dari laba '.Format::rupiah($profit)],
        ];
    }

    /**
     * Stock that needs buying: below its minimum, or gone within 3 days at
     * the average daily use of the last 7 days. daysLeft is null while the
     * books hold fewer than 3 days ("data belum cukup").
     *
     * @return list<array{id: int, name: string, unit: string, stock: float, minimum: float|null, daysLeft: float|null}>
     */
    public function stockAlerts(Business $business): array
    {
        $first = $business->firstRecordAt();
        $daysOfData = $first === null ? 0 : $first->diffInDays(now());
        $since = now()->subDays(7);

        $used = DB::table('stock_movements')
            ->join('transactions', 'transactions.id', '=', 'stock_movements.transaction_id')
            ->where('stock_movements.business_id', $business->id)
            ->where('stock_movements.reason', StockReason::Penjualan->value)
            ->where('transactions.status', TransactionStatus::Posted->value)
            ->where('stock_movements.occurred_at', '>=', $since)
            ->groupBy('stock_movements.item_id')
            ->selectRaw('stock_movements.item_id, sum(stock_movements.qty) as qty')
            ->pluck('qty', 'item_id')
            ->map(fn ($qty) => -(float) $qty);

        $alerts = $business->items()->stocked()
            ->where(fn ($query) => $query->whereNotNull('items.min_stock')->orWhereIn('items.id', $used->keys()))
            ->orderBy('items.name')
            ->get()
            ->map(function (Item $item) use ($used, $daysOfData) {
                $perDay = ($used[$item->id] ?? 0) / max(1, min(7, $daysOfData));
                $daysLeft = $daysOfData >= 3 && $perDay > 0 ? round(max(0, (float) $item->stock) / $perDay, 1) : null;
                $alerting = $item->isLowStock() || $item->isNegativeStock() || ($daysLeft !== null && $daysLeft <= 3);

                return $alerting ? [
                    'id' => $item->id,
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'stock' => (float) $item->stock,
                    'minimum' => $item->min_stock === null ? null : (float) $item->min_stock,
                    'daysLeft' => $daysLeft,
                ] : null;
            })
            ->filter()
            ->sortBy(fn (array $alert) => $alert['daysLeft'] ?? INF)
            ->all();

        return array_values($alerts);
    }

    /**
     * Sales per WIB day for the last $days days, today last; quiet days are 0.
     *
     * @return list<array{date: string, sales: int}>
     */
    public function salesByDay(Business $business, int $days = 30): array
    {
        $from = today()->subDays($days - 1);

        $byDay = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.business_id', $business->id)
            ->where('accounts.code', Account::SALES)
            ->where('journal_entries.occurred_at', '>=', $from)
            ->groupByRaw('date(journal_entries.occurred_at)')
            ->selectRaw('date(journal_entries.occurred_at) as day, sum(journal_lines.debit) as debit, sum(journal_lines.credit) as credit')
            ->get()
            ->mapWithKeys(fn (object $row) => [(string) $row->day => (int) $row->credit - (int) $row->debit]);

        return array_map(function (int $back) use ($byDay) {
            $date = today()->subDays($back)->toDateString();

            return ['date' => $date, 'sales' => $byDay[$date] ?? 0];
        }, range($days - 1, 0));
    }

    /**
     * Biaya and pembelian per month, averaged over the last three months at
     * most, counted from the first record.
     */
    private function monthlyOutflow(Business $business): int
    {
        $first = $business->firstRecordAt();

        if ($first === null) {
            return 0;
        }

        $since = $first->max(now()->subDays(90));
        $months = min(3, max(1, (int) ceil($since->diffInDays(now()) / 30)));
        $total = (int) $business->transactions()
            ->where('transactions.status', TransactionStatus::Posted)
            ->whereIn('transactions.type', [TransactionType::Biaya, TransactionType::Pembelian])
            ->where('transactions.occurred_at', '>=', $since)
            ->sum('transactions.total');

        return intdiv($total, $months);
    }

    /**
     * Journal lines of one business in a time range, joined to their accounts.
     */
    private function journal(Business $business, ?CarbonInterface $from, ?CarbonInterface $before): Builder
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.business_id', $business->id)
            ->when($from, fn ($query) => $query->where('journal_entries.occurred_at', '>=', $from))
            ->when($before, fn ($query) => $query->where('journal_entries.occurred_at', '<', $before));
    }

    /**
     * Where a period's own movements begin: just after the opening balance
     * sheet when the books open inside the period, so saldo awal is where
     * the period starts and never counts as a deposit.
     */
    private function periodStart(Business $business, CarbonInterface $from, ?CarbonInterface $before): CarbonInterface
    {
        $opened = $business->started_on?->toImmutable()->startOfDay();

        return $opened !== null && $opened >= $from && ($before === null || $opened < $before) ? $opened->addSecond() : $from;
    }

    /**
     * Laba bersih from account nets: every income and expense account,
     * including the ones a business added, is coded from 4000 up.
     *
     * @param  array<int, int>  $net
     */
    private static function profit(array $net): int
    {
        return -array_sum(array_filter($net, fn (int $code) => $code >= 4000, ARRAY_FILTER_USE_KEY));
    }

    /**
     * Assets (1xxx) minus liabilities (2xxx) of the fixed chart (§4.4); the
     * journal balances, so this equals the equity side.
     *
     * @param  array<int, int>  $net
     */
    private static function assetsLessLiabilities(array $net): int
    {
        return array_sum(array_filter($net, fn (int $code) => $code < 3000, ARRAY_FILTER_USE_KEY));
    }
}
