<?php

namespace App\Services\WhatsApp;

use App\Enums\OrderStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Business;
use App\Services\Ledger;
use App\Services\Reports;
use App\Support\Format;
use Carbon\CarbonImmutable;

/**
 * The evening summary (PRD M8, US-15), from the journal and fixed words:
 * today's sales and profit, cash in and out, money on hand, orders still
 * open, stock about to run out, and health rules that fail.
 */
class DailySummary
{
    public function __construct(private Reports $reports, private Ledger $ledger) {}

    public function for(Business $business, ?CarbonImmutable $day = null): string
    {
        $day ??= CarbonImmutable::now()->startOfDay();
        $next = $day->addDay();
        $pnl = $this->reports->profitAndLoss($business, $day, $next);
        $count = $this->reports->salesCount($business, $day, $next);
        $flows = $business->transactions()
            ->where('transactions.status', TransactionStatus::Posted)
            ->where('transactions.type', '<>', TransactionType::SaldoAwal)
            ->where('transactions.occurred_at', '>=', $day)
            ->where('transactions.occurred_at', '<', $next)
            ->pluck('transactions.cash_flow');
        $cash = array_sum($this->ledger->moneyBalances($business));
        $open = $business->orders()->whereIn('orders.status', [OrderStatus::Menunggu, OrderStatus::Diproses, OrderStatus::Siap])->count();
        $runningOut = array_filter($this->reports->stockAlerts($business), fn (array $alert) => $alert['stock'] <= 0 || ($alert['daysLeft'] !== null && $alert['daysLeft'] <= 3));
        $failing = array_filter($this->reports->healthChecks($business), fn (array $rule) => ! $rule['passed']);

        $lines = [
            "*Ringkasan {$business->name}, ".Format::date($day).'*',
            'Penjualan: '.Format::rupiah($pnl['sales'])." ({$count} transaksi)",
            ($pnl['netProfit'] < 0 ? 'Rugi' : 'Laba').' bersih hari ini: '.Format::rupiah(abs($pnl['netProfit'])),
            'Uang masuk '.Format::rupiah((int) $flows->filter(fn ($flow) => $flow > 0)->sum()).', keluar '.Format::rupiah((int) -$flows->filter(fn ($flow) => $flow < 0)->sum()),
            'Uang usaha sekarang: '.Format::rupiah($cash),
        ];

        if ($open > 0) {
            $lines[] = "Pesanan belum selesai: {$open}";
        }

        if ($runningOut !== []) {
            $lines[] = "\nStok mau habis:";

            foreach (array_slice($runningOut, 0, 5) as $alert) {
                $lines[] = '• '.($alert['stock'] <= 0
                    ? "{$alert['name']} sudah habis"
                    : "{$alert['name']} habis ±".Format::qty((float) $alert['daysLeft']).' hari lagi (sisa '.Format::qty($alert['stock'])." {$alert['unit']})");
            }
        }

        if ($failing !== []) {
            $lines[] = "\nPerlu dicek:";

            foreach ($failing as $rule) {
                $lines[] = "• {$rule['label']}: belum ({$rule['detail']})";
            }
        }

        $lines[] = "\nDashboard: ".route('dashboard');

        return implode("\n", $lines);
    }
}
