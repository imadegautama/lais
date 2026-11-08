<?php

namespace App\Http\Controllers;

use App\Enums\ContactType;
use App\Enums\ItemKind;
use App\Enums\OrderStatus;
use App\Enums\Period;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Order;
use App\Models\User;
use App\Services\Debts;
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
    public function __invoke(Request $request, Reports $reports, Ledger $ledger, Debts $debts): Response
    {
        $business = $request->user()->currentBusiness();
        $period = Period::tryFrom((string) $request->query('periode')) ?? Period::HariIni;
        $from = $period->start();
        $before = $period->end();
        [$previousFrom, $previousBefore] = $period->previous();
        // Compared only once the books reach back into the period before.
        $compare = ($business->firstRecordAt() ?? CarbonImmutable::now()) < $previousBefore;
        $monthStart = now()->startOfMonth();

        $profit = $reports->profitAndLoss($business, $from, $before);
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
            'missions' => $this->missions($business, $request->user()),
            // Right after the setup: the tour runs once (ProductTour remembers it per device).
            'startTour' => $request->boolean('tur'),
            'period' => ['key' => $period, 'label' => $period->label(), 'comparisonLabel' => $compare ? $period->comparisonLabel() : null],
            'periods' => array_map(fn (Period $option) => ['key' => $option, 'label' => $option->label()], Period::cases()),
            'summary' => [
                'netProfit' => ['value' => $profit['netProfit'], 'previous' => $previous['netProfit'] ?? null],
                'sales' => ['value' => $profit['sales'], 'previous' => $previous['sales'] ?? null, 'count' => $reports->salesCount($business, $from, $before)],
                'cash' => ['total' => $onHand + $nonCash, 'onHand' => $onHand, 'nonCash' => $nonCash],
                'personalDraw' => [
                    'value' => $reports->drawings($business, $from, $before),
                    'previous' => $compare ? $reports->drawings($business, $previousFrom, $previousBefore) : null,
                ],
            ],
            'actions' => [...$this->orderActions($business), ...$this->stockActions($stockAlerts), ...$this->receivableActions($debts->summary($business, ContactType::Pelanggan))],
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
     * New orders from the shop, oldest first: they wait for Terima or Tolak.
     *
     * @return list<array<string, mixed>>
     */
    private function orderActions(Business $business): array
    {
        return array_values($business->orders()->where('orders.status', OrderStatus::Menunggu)->oldest('orders.id')->limit(3)->get()
            ->map(fn (Order $order) => [
                'id' => "pesanan-{$order->id}",
                'kind' => 'pesanan',
                'title' => "Pesanan {$order->code} dari {$order->customer_name}",
                'detail' => Format::rupiah($order->total).', masuk '.Format::date($order->created_at).' '.$order->created_at->format('H:i'),
                'actionLabel' => 'Lihat pesanan',
                'target' => ['href' => route('orders.show', $order->code)],
            ])->all());
    }

    /**
     * Stock already out, or gone within two days: buy more.
     *
     * @param  list<array{id: int, name: string, unit: string, stock: float, minimum: float|null, daysLeft: float|null}>  $alerts
     * @return list<array<string, mixed>>
     */
    private function stockActions(array $alerts): array
    {
        $soon = array_filter($alerts, fn (array $alert) => $alert['stock'] <= 0 || ($alert['daysLeft'] !== null && $alert['daysLeft'] <= 2));

        return array_values(array_map(fn (array $alert) => [
            'id' => "stok-{$alert['id']}",
            'kind' => 'stok',
            // Stock already at or below zero is out now, not "gone in ±0 days".
            'title' => $alert['stock'] <= 0 ? "{$alert['name']} sudah habis" : "{$alert['name']} habis ±".Format::qty($alert['daysLeft']).' hari lagi',
            'detail' => ($alert['stock'] < 0 ? 'Stok tercatat ' : 'Sisa ').Format::qty($alert['stock'])." {$alert['unit']}"
                .($alert['minimum'] === null ? '' : ', batas minimum '.Format::qty($alert['minimum'])." {$alert['unit']}"),
            'actionLabel' => 'Catat belanja',
            'target' => ['href' => route('transactions.create', ['jenis' => TransactionType::Pembelian->value])],
        ], $soon));
    }

    /**
     * Customers more than 7 days behind, the three oldest.
     *
     * @param  list<array{contact: Contact, balance: int, since: CarbonImmutable, open: int}>  $receivables
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
            'actionLabel' => 'Lihat tagihan',
            'target' => ['href' => route('debts.show', $row['contact'])],
        ], array_slice(array_values($overdue), 0, 3));
    }

    /**
     * "Misi pertama": what makes Lais work fully for a new business, each
     * ticked from the data itself, so nothing has to be marked by hand.
     *
     * @return list<array{key: string, done: bool}>
     */
    private function missions(Business $business, User $owner): array
    {
        $posted = fn (TransactionType $type) => $business->transactions()
            ->where('transactions.status', TransactionStatus::Posted)
            ->where('transactions.type', $type)
            ->exists();

        return [
            ['key' => 'produk', 'done' => $business->items()->whereIn('items.kind', ItemKind::sold())->exists()],
            ['key' => 'jual', 'done' => $posted(TransactionType::Penjualan)],
            ['key' => 'biaya', 'done' => $posted(TransactionType::Biaya)],
            ['key' => 'toko', 'done' => $business->whatsapp !== null || $business->qris_path !== null],
            ['key' => 'bot', 'done' => $owner->whatsapp_linked_at !== null],
        ];
    }
}
