<?php

namespace App\Services;

use App\Enums\StockReason;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Item;
use App\Models\Transaction;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The numbers behind the dashboard, the reports (PRD M6) and the AI's read
 * tools (§3.1), all read from the journal so they always agree. A cancelled
 * record is mirrored on its own date, so every period nets it to zero.
 */
class Reports
{
    /**
     * Net amount per account in a time range: + debit, − credit. $from is
     * inclusive, $before exclusive.
     *
     * @return array<int, int> account code => rupiah
     */
    public function net(Business $business, ?CarbonInterface $from = null, ?CarbonInterface $before = null): array
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.business_id', $business->id)
            ->when($from, fn ($query) => $query->where('journal_entries.occurred_at', '>=', $from))
            ->when($before, fn ($query) => $query->where('journal_entries.occurred_at', '<', $before))
            ->groupBy('accounts.code')
            // Debit and credit are unsigned, so each side is summed before subtracting (MySQL rejects a negative unsigned).
            ->selectRaw('accounts.code, sum(journal_lines.debit) as debit, sum(journal_lines.credit) as credit')
            ->get()
            ->mapWithKeys(fn (object $row) => [(int) $row->code => (int) $row->debit - (int) $row->credit])
            ->all();
    }

    /**
     * Laba rugi: sales − HPP = gross profit, − operating costs − stock
     * differences = net profit.
     *
     * @return array{sales: int, cogs: int, grossProfit: int, expenses: int, stockDifference: int, netProfit: int}
     */
    public function profitAndLoss(Business $business, CarbonInterface $from, ?CarbonInterface $before = null): array
    {
        $net = $this->net($business, $from, $before);
        $sales = -($net[Account::SALES] ?? 0);
        $cogs = $net[Account::COGS] ?? 0;
        $expenses = $net[Account::EXPENSES] ?? 0;
        $stockDifference = $net[Account::STOCK_DIFFERENCE] ?? 0;

        return [
            'sales' => $sales,
            'cogs' => $cogs,
            'grossProfit' => $sales - $cogs,
            'expenses' => $expenses,
            'stockDifference' => $stockDifference,
            'netProfit' => $sales - $cogs - $expenses - $stockDifference,
        ];
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
    public function openingEquity(Business $business, CarbonInterface $from): int
    {
        $opened = $business->started_on?->toImmutable()->startOfDay();

        return $this->equityAt($business, $opened !== null && $opened >= $from ? $opened->addSecond() : $from);
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
     * Customers who still owe, with the date of the oldest sale not yet paid
     * off: payments settle the oldest debts first. Oldest first.
     *
     * @return list<array{contact: Contact, balance: int, since: CarbonImmutable}>
     */
    public function openReceivables(Business $business): array
    {
        $lines = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('transactions', function ($join) {
                $join->on('transactions.id', '=', 'journal_entries.source_id')
                    ->where('journal_entries.source_type', (new Transaction)->getMorphClass());
            })
            ->where('journal_entries.business_id', $business->id)
            ->where('accounts.code', Account::RECEIVABLE)
            // A cancelled record and its mirror both drop out, so they never shift the age.
            ->where('transactions.status', TransactionStatus::Posted->value)
            ->whereNotNull('transactions.contact_id')
            ->orderBy('journal_entries.occurred_at')
            ->orderBy('journal_entries.id')
            ->get(['transactions.contact_id', 'journal_entries.occurred_at', 'journal_lines.debit', 'journal_lines.credit']);

        $open = [];

        foreach ($lines->groupBy('contact_id') as $contactId => $contactLines) {
            $paid = (int) $contactLines->sum('credit');
            $balance = (int) $contactLines->sum('debit') - $paid;

            if ($balance <= 0) {
                continue;
            }

            foreach ($contactLines as $line) {
                $paid -= (int) $line->debit;

                if ($paid < 0) {
                    $open[(int) $contactId] = ['balance' => $balance, 'since' => CarbonImmutable::parse($line->occurred_at)];
                    break;
                }
            }
        }

        $rows = Contact::query()->whereKey(array_keys($open))->get()
            ->map(fn (Contact $contact) => ['contact' => $contact] + $open[$contact->id])
            ->sortBy(fn (array $row) => $row['since']->getTimestamp())
            ->all();

        return array_values($rows);
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
