<?php

namespace App\Http\Controllers;

use App\Enums\Period;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Contact;
use App\Services\Ledger;
use App\Services\Reports;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Dashboard (UI-PAGES C1): how the business stands now, from the journal.
     */
    public function __invoke(Request $request, Reports $reports, Ledger $ledger): Response
    {
        $business = $request->user()->currentBusiness();
        $period = Period::tryFrom((string) $request->query('periode')) ?? Period::HariIni;
        $from = $period->start();
        [$previousFrom, $previousBefore] = $period->previous();
        // Compared only once the books reach back into the period before.
        $compare = ($business->firstRecordAt() ?? CarbonImmutable::now()) < $previousBefore;
        $monthStart = now()->startOfMonth();

        $profit = $reports->profitAndLoss($business, $from);
        $previous = $compare ? $reports->profitAndLoss($business, $previousFrom, $previousBefore) : null;
        $onHand = $ledger->balance($business, Account::CASH);
        $nonCash = $ledger->balance($business, Account::NON_CASH);
        $stockAlerts = $reports->stockAlerts($business);

        return Inertia::render('dashboard', [
            // Until the books are opened, the dashboard asks for the saldo awal.
            'needsOnboarding' => $business->onboarded_at === null,
            'isEmpty' => ! $business->transactions()
                ->where('transactions.status', TransactionStatus::Posted)
                ->where('transactions.type', '<>', TransactionType::SaldoAwal)
                ->exists(),
            'business' => ['name' => $business->name, 'ownerName' => $request->user()->name],
            'period' => ['key' => $period, 'label' => $period->label(), 'comparisonLabel' => $compare ? $period->comparisonLabel() : null],
            'periods' => array_map(fn (Period $option) => ['key' => $option, 'label' => $option->label()], Period::cases()),
            'summary' => [
                'netProfit' => ['value' => $profit['netProfit'], 'previous' => $previous['netProfit'] ?? null],
                'sales' => ['value' => $profit['sales'], 'previous' => $previous['sales'] ?? null, 'count' => $reports->salesCount($business, $from)],
                'cash' => ['total' => $onHand + $nonCash, 'onHand' => $onHand, 'nonCash' => $nonCash],
                'personalDraw' => [
                    'value' => $reports->drawings($business, $from),
                    'previous' => $compare ? $reports->drawings($business, $previousFrom, $previousBefore) : null,
                ],
            ],
            'actions' => [...$this->stockActions($stockAlerts), ...$this->receivableActions($reports->openReceivables($business))],
            'wallets' => [
                'businessCash' => $onHand + $nonCash,
                'drawnThisMonth' => $reports->drawings($business, $monthStart),
                'netProfitThisMonth' => $reports->profitAndLoss($business, $monthStart)['netProfit'],
                'equityOpening' => $reports->openingEquity($business, $monthStart),
                'equityNow' => $reports->equityAt($business),
            ],
            'health' => $reports->healthChecks($business),
            'stockAlerts' => $stockAlerts,
            'salesChart' => $reports->salesByDay($business, 30),
            'recentTransactions' => TransactionResource::collection(
                $business->transactions()->with(['contact', 'moneyAccount', 'toMoneyAccount'])->latest('occurred_at')->latest('id')->limit(8)->get(),
            )->resolve(),
        ]);
    }

    /**
     * Stock gone within two days: buy more.
     *
     * @param  list<array{id: int, name: string, unit: string, stock: float, minimum: float|null, daysLeft: float|null}>  $alerts
     * @return list<array<string, mixed>>
     */
    private function stockActions(array $alerts): array
    {
        $soon = array_filter($alerts, fn (array $alert) => $alert['daysLeft'] !== null && $alert['daysLeft'] <= 2);

        return array_values(array_map(fn (array $alert) => [
            'id' => "stok-{$alert['id']}",
            'kind' => 'stok',
            'title' => "{$alert['name']} habis ±".Format::qty($alert['daysLeft']).' hari lagi',
            'detail' => 'Sisa '.Format::qty($alert['stock'])." {$alert['unit']}"
                .($alert['minimum'] === null ? '' : ', batas minimum '.Format::qty($alert['minimum'])." {$alert['unit']}"),
            'actionLabel' => 'Catat belanja',
            'target' => ['href' => route('transactions.create', ['jenis' => TransactionType::Pembelian->value])],
        ], $soon));
    }

    /**
     * Customers more than 7 days behind, the three oldest.
     *
     * @param  list<array{contact: Contact, balance: int, since: CarbonImmutable}>  $receivables
     * @return list<array<string, mixed>>
     */
    private function receivableActions(array $receivables): array
    {
        $overdue = array_filter($receivables, fn (array $row) => $row['since'] <= now()->subDays(7));

        return array_map(fn (array $row) => [
            'id' => "piutang-{$row['contact']->id}",
            'kind' => 'piutang',
            'title' => "{$row['contact']->name} belum bayar ".Format::rupiah($row['balance']),
            'detail' => 'Belum lunas sejak '.Format::date($row['since']).', sudah '.(int) $row['since']->diffInDays(now()).' hari',
            'actionLabel' => 'Catat bayaran',
            'target' => ['href' => route('transactions.create', ['jenis' => TransactionType::UtangPiutang->value])],
        ], array_slice(array_values($overdue), 0, 3));
    }
}
