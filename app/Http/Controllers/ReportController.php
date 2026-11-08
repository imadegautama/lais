<?php

namespace App\Http\Controllers;

use App\Enums\ContactType;
use App\Enums\Period;
use App\Models\Account;
use App\Models\Business;
use App\Services\Debts;
use App\Services\Depreciation;
use App\Services\FinancialNotes;
use App\Services\Ledger;
use App\Services\Reports;
use App\Services\ReportStory;
use App\Support\ReportRange;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Laporan (UI-PAGES C13 to C17), read from the journal through Reports, the
 * same methods the dashboard and the AI use. Printing is the browser's.
 */
class ReportController extends Controller
{
    public function __construct(private Reports $reports, private ReportStory $story) {}

    /**
     * Pusat laporan: how the business is doing, then one real figure per report.
     */
    public function index(Request $request, Ledger $ledger, Debts $debts, Depreciation $depreciation): Response
    {
        [$business, $range] = $this->scope($request);
        $now = $this->reports->profitAndLoss($business, $range->from, $range->before);
        $earlier = $this->reports->profitAndLoss($business, $range->priorFrom, $range->priorBefore);
        $position = $this->reports->balanceSheet($business, now());
        $money = (int) array_sum($ledger->moneyBalances($business));
        $owed = (int) array_sum(array_column($debts->summary($business, ContactType::Pelanggan), 'balance'));
        $products = $this->reports->productPerformance($business, $range->from, $range->before);
        $book = $this->reports->cashBook($business, $range->from, $range->before);
        $chart = $this->reports->chart($business);

        return Inertia::render('reports/index', [
            ...$this->frame($business, $range),
            'story' => $this->story->overview($now, $earlier, $money, $owed, $range),
            'trend' => $this->reports->monthlyTrend($business),
            'netProfit' => $now['netProfit'],
            'previousNetProfit' => $earlier['netProfit'],
            'sales' => $now['sales'],
            'closingEquity' => $this->reports->equityChanges($business, $range->from, $range->before)['closing'],
            'balanceSheet' => $position,
            'cashChange' => $book['closing'] - $book['opening'],
            'money' => $money,
            'topProduct' => $products[0]['name'] ?? null,
            'equipmentValue' => $position['assets']['equipment'] + $position['assets']['accumulatedDepreciation'],
            'accountCount' => count($chart),
            'balancedTotal' => (int) array_sum(array_column($chart, 'debit')),
            'unsetAssets' => count(array_filter($depreciation->book($business), fn (array $asset) => $asset['lifeMonths'] === null)),
        ]);
    }

    public function profitAndLoss(Request $request): Response
    {
        [$business, $range] = $this->scope($request);
        $statement = fn (CarbonImmutable $from, ?CarbonImmutable $before) => [
            ...$this->reports->profitAndLoss($business, $from, $before),
            'expensesByKind' => $this->reports->expensesByKind($business, $from, $before),
            'ownAccounts' => $this->reports->ownAccounts($business, $from, $before),
        ];
        $report = $statement($range->from, $range->before);
        $previous = $statement($range->priorFrom, $range->priorBefore);

        return Inertia::render('reports/profit-and-loss', [
            ...$this->frame($business, $range),
            'story' => $this->story->profit($report, $previous, $range),
            'report' => $report,
            'previous' => $previous,
            'trend' => $this->reports->monthlyTrend($business),
        ]);
    }

    public function equity(Request $request): Response
    {
        [$business, $range] = $this->scope($request);
        $reconciliation = $this->reports->cashReconciliation($business, $range->from, $range->before);

        return Inertia::render('reports/equity', [
            ...$this->frame($business, $range),
            'story' => $this->story->profitVsCash($reconciliation, $range),
            'report' => $this->reports->equityChanges($business, $range->from, $range->before),
            'reconciliation' => $reconciliation,
        ]);
    }

    /**
     * Laporan posisi keuangan per tanggal (?tanggal=Y-m-d), today when absent,
     * unreadable, or in the future, beside the end of the month before.
     */
    public function balanceSheet(Request $request): Response
    {
        [$business, $range] = $this->scope($request);
        $today = now()->toImmutable()->startOfDay();
        $date = self::date($request);
        $previous = $date->subMonthNoOverflow()->endOfMonth()->startOfDay();
        $report = $this->reports->balanceSheet($business, $date);

        return Inertia::render('reports/balance-sheet', [
            ...$this->frame($business, $range),
            'story' => $this->story->position($report),
            'date' => $date->toDateString(),
            'today' => $today->toDateString(),
            'report' => $report,
            'previous' => $this->reports->balanceSheet($business, $previous),
            'previousDate' => $previous->toDateString(),
        ]);
    }

    /**
     * Buku kas of the chosen range, for all rekening or one (?rekening=id,
     * looked up within this business only).
     */
    public function cashBook(Request $request): Response
    {
        [$business, $range] = $this->scope($request);
        $accounts = $business->moneyAccounts()->withTrashed()->orderBy('money_accounts.id')->get(['money_accounts.id', 'money_accounts.name']);
        $chosen = $accounts->firstWhere('id', (int) $request->query('rekening'))?->id;
        $book = $this->reports->cashBook($business, $range->from, $range->before, $chosen);

        return Inertia::render('reports/cash-book', [
            ...$this->frame($business, $range),
            'story' => $this->story->cash($book, $range),
            'moneyAccounts' => $accounts->map(fn ($account) => ['id' => $account->id, 'name' => $account->name])->values()->all(),
            'moneyAccountId' => $chosen,
            'book' => $book,
        ]);
    }

    public function products(Request $request): Response
    {
        [$business, $range] = $this->scope($request);
        $products = $this->reports->productPerformance($business, $range->from, $range->before);
        $slow = $this->reports->slowStock($business);

        return Inertia::render('reports/products', [
            ...$this->frame($business, $range),
            'story' => $this->story->products($products, $slow, $range),
            'products' => $products,
            'slow' => $slow,
        ]);
    }

    /**
     * Daftar akun: the chart with today's balances, for the owner to add to.
     */
    public function chart(Request $request): Response
    {
        [$business, $range] = $this->scope($request);

        return Inertia::render('reports/chart', [
            ...$this->frame($business, $range),
            'accounts' => $this->reports->chart($business),
        ]);
    }

    /**
     * Neraca saldo per tanggal: every account's debit or credit, adding up
     * to the same total.
     */
    public function trialBalance(Request $request): Response
    {
        [$business, $range] = $this->scope($request);
        $date = self::date($request);

        $accounts = array_values(array_filter($this->reports->chart($business, $date), fn (array $row) => $row['debit'] !== 0 || $row['credit'] !== 0));

        return Inertia::render('reports/trial-balance', [
            ...$this->frame($business, $range),
            'date' => $date->toDateString(),
            'today' => now()->toDateString(),
            'story' => $this->story->trialBalance((int) array_sum(array_column($accounts, 'debit')), (int) array_sum(array_column($accounts, 'credit'))),
            'accounts' => $accounts,
        ]);
    }

    /**
     * Buku besar of one account, looked up within this business only.
     */
    public function ledger(Request $request, string $code): Response
    {
        [$business, $range] = $this->scope($request);
        $account = Account::query()->visibleTo($business)->where('accounts.code', $code)->firstOrFail();

        return Inertia::render('reports/ledger', [
            ...$this->frame($business, $range),
            'account' => ['code' => $account->code, 'name' => $account->name],
            'ledger' => $this->reports->ledger($business, $account, $range->from, $range->before),
        ]);
    }

    /**
     * Jurnal umum: every entry of the range, which is where the other reports come from.
     */
    public function journal(Request $request): Response
    {
        [$business, $range] = $this->scope($request);
        $entries = $this->reports->journalEntries($business, $range->from, $range->before);
        $lines = array_merge(...array_column($entries, 'lines'), ...[[]]);

        return Inertia::render('reports/journal', [
            ...$this->frame($business, $range),
            'story' => $this->story->trialBalance((int) array_sum(array_column($lines, 'debit')), (int) array_sum(array_column($lines, 'credit'))),
            'entries' => $entries,
        ]);
    }

    /**
     * Catatan atas laporan keuangan (SAK EMKM par. 6.2): the details as they
     * stand today, and the expenses of the chosen range beside the one before.
     */
    public function notes(Request $request, FinancialNotes $notes): Response
    {
        [$business, $range] = $this->scope($request);

        return Inertia::render('reports/notes', [
            ...$this->frame($business, $range),
            'category' => $business->category?->label(),
            'today' => now()->toDateString(),
            'position' => $this->reports->balanceSheet($business, now()),
            'details' => $notes->details($business),
            'expensesByKind' => $this->reports->expensesByKind($business, $range->from, $range->before),
            'previousExpensesByKind' => $this->reports->expensesByKind($business, $range->priorFrom, $range->priorBefore),
        ]);
    }

    /**
     * ?tanggal=Y-m-d, today when absent, unreadable, or in the future.
     */
    private static function date(Request $request): CarbonImmutable
    {
        $today = now()->toImmutable()->startOfDay();
        $date = rescue(fn () => CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->query('tanggal')), null, false);

        return $date instanceof CarbonImmutable && $date <= $today ? $date : $today;
    }

    /**
     * @return array{0: Business, 1: ReportRange}
     */
    private function scope(Request $request): array
    {
        return [$request->user()->currentBusiness(), ReportRange::fromRequest($request)];
    }

    /**
     * What every report page shows around its numbers: the business for the
     * printed header, the range and its choices, and when the books began.
     *
     * @return array<string, mixed>
     */
    private function frame(Business $business, ReportRange $range): array
    {
        return [
            'business' => ['name' => $business->name],
            'period' => ['key' => $range->period, 'label' => $range->label],
            'previousLabel' => $range->priorLabel,
            'periods' => array_map(fn (Period $option) => ['key' => $option, 'label' => $option->label()], Period::cases()),
            'range' => ['from' => $range->from->toDateString(), 'to' => $range->lastDay()->toDateString()],
            'query' => $range->query(),
            'startedOn' => $business->started_on?->toDateString(),
        ];
    }
}
