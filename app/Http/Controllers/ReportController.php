<?php

namespace App\Http\Controllers;

use App\Enums\Period;
use App\Models\Business;
use App\Services\Reports;
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
    /**
     * Pusat laporan: the key number of each report for the chosen period.
     */
    public function index(Request $request, Reports $reports): Response
    {
        [$business, $period] = $this->scope($request);

        return Inertia::render('reports/index', [
            ...$this->frame($business, $period),
            'netProfit' => $reports->profitAndLoss($business, $period->start(), $period->end())['netProfit'],
            'closingEquity' => $reports->equityChanges($business, $period->start(), $period->end())['closing'],
            'balanceSheet' => $reports->balanceSheet($business, now()),
        ]);
    }

    public function profitAndLoss(Request $request, Reports $reports): Response
    {
        [$business, $period] = $this->scope($request);

        return Inertia::render('reports/profit-and-loss', [
            ...$this->frame($business, $period),
            'report' => [
                ...$reports->profitAndLoss($business, $period->start(), $period->end()),
                'expensesByKind' => $reports->expensesByKind($business, $period->start(), $period->end()),
            ],
        ]);
    }

    public function equity(Request $request, Reports $reports): Response
    {
        [$business, $period] = $this->scope($request);

        return Inertia::render('reports/equity', [
            ...$this->frame($business, $period),
            'report' => $reports->equityChanges($business, $period->start(), $period->end()),
            'reconciliation' => $reports->cashReconciliation($business, $period->start(), $period->end()),
        ]);
    }

    /**
     * Neraca per tanggal (?tanggal=Y-m-d), today when absent, unreadable, or
     * in the future.
     */
    public function balanceSheet(Request $request, Reports $reports): Response
    {
        [$business, $period] = $this->scope($request);
        $today = now()->toImmutable()->startOfDay();
        $date = rescue(fn () => CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->query('tanggal')), null, false);
        $date = $date instanceof CarbonImmutable && $date <= $today ? $date : $today;

        return Inertia::render('reports/balance-sheet', [
            ...$this->frame($business, $period),
            'date' => $date->toDateString(),
            'today' => $today->toDateString(),
            'report' => $reports->balanceSheet($business, $date),
        ]);
    }

    /**
     * @return array{0: Business, 1: Period}
     */
    private function scope(Request $request): array
    {
        return [$request->user()->currentBusiness(), Period::tryFrom((string) $request->query('periode')) ?? Period::BulanIni];
    }

    /**
     * What every report page shows around its numbers: the business for the
     * printed header, the period and its choices, and when the books began.
     *
     * @return array<string, mixed>
     */
    private function frame(Business $business, Period $period): array
    {
        return [
            'business' => ['name' => $business->name],
            'period' => ['key' => $period, 'label' => $period->label()],
            'periods' => array_map(fn (Period $option) => ['key' => $option, 'label' => $option->label()], Period::cases()),
            'startedOn' => $business->started_on?->toDateString(),
        ];
    }
}
