<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Business;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Straight-line depreciation with no residual value (SAK EMKM par. 11.12):
 * a full month from the month the asset was acquired, the rounding left over
 * falling in the last month. Each month is posted once, dated its last day.
 */
class Depreciation
{
    public function __construct(private Ledger $ledger) {}

    /**
     * Every month that has ended by $through, for one business or all.
     *
     * @return int months posted
     */
    public function run(?CarbonImmutable $through = null, ?Business $business = null): int
    {
        $through ??= now()->toImmutable()->startOfMonth()->subDay()->endOfDay();

        return (int) FixedAsset::query()
            ->whereNull('fixed_assets.cancelled_at')
            ->whereNotNull('fixed_assets.useful_life_months')
            ->when($business, fn ($query, Business $business) => $query->where('fixed_assets.business_id', $business->id))
            ->orderBy('fixed_assets.id')
            ->get()
            ->sum(fn (FixedAsset $asset) => $this->post($asset, $through));
    }

    /**
     * Catches one asset up to $through. Safe to repeat: the row remembers the
     * last month posted, and is locked while it moves.
     *
     * @return int months posted
     */
    public function post(FixedAsset $asset, CarbonImmutable $through): int
    {
        return DB::transaction(function () use ($asset, $through) {
            $asset = FixedAsset::query()->lockForUpdate()->findOrFail($asset->id);
            $life = $asset->useful_life_months;

            if ($life === null || $asset->cancelled_at !== null) {
                return 0;
            }

            $start = $asset->acquired_on->startOfMonth();
            $last = $asset->depreciated_through;
            $done = $last === null ? 0 : ($last->year - $start->year) * 12 + $last->month - $start->month + 1;
            $monthly = intdiv($asset->cost, $life);
            $posted = 0;

            for ($index = $done; $index < $life; $index++) {
                $end = $start->addMonthsNoOverflow($index)->endOfMonth();

                if ($end > $through) {
                    break;
                }

                $amount = $index === $life - 1 ? $asset->cost - $monthly * ($life - 1) : $monthly;

                // A cost smaller than its months has nothing to post until the last one.
                if ($amount > 0) {
                    $this->ledger->post($asset->business, mb_strimwidth("Penyusutan {$asset->name} {$end->format('m/Y')}", 0, 255, '…'), Ledger::lines([
                        Account::DEPRECIATION => $amount,
                        Account::ACCUMULATED_DEPRECIATION => -$amount,
                    ]), $asset, $end);
                }

                $asset->depreciated_through = $end;
                $posted++;
            }

            $asset->save();

            return $posted;
        });
    }

    /**
     * The purchase was cancelled, so the asset never was: each month's journal
     * is mirrored on its own date, leaving every period at zero.
     */
    public function cancel(FixedAsset $asset): void
    {
        $entries = JournalEntry::query()
            ->where('journal_entries.source_type', $asset->getMorphClass())
            ->where('journal_entries.source_id', $asset->id)
            ->with('lines.account')
            ->get();

        foreach ($entries as $entry) {
            $amounts = [];

            foreach ($entry->lines as $line) {
                $amounts[$line->account->code] = $line->credit - $line->debit;
            }

            $this->ledger->post($asset->business, mb_strimwidth("Pembatalan: {$entry->description}", 0, 255, '…'), Ledger::lines($amounts), null, $entry->occurred_at);
        }

        $asset->update(['cancelled_at' => now()]);
    }

    /**
     * Each asset with what has been depreciated so far, read from the journal.
     *
     * @return list<array{id: int, name: string, cost: int, acquiredOn: string, lifeMonths: int|null, accumulated: int, bookValue: int, locked: bool}>
     */
    public function book(Business $business): array
    {
        $accumulated = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.business_id', $business->id)
            ->where('journal_entries.source_type', (new FixedAsset)->getMorphClass())
            ->where('accounts.code', Account::ACCUMULATED_DEPRECIATION)
            ->groupBy('journal_entries.source_id')
            ->selectRaw('journal_entries.source_id, sum(journal_lines.credit) as credit, sum(journal_lines.debit) as debit')
            ->get()
            ->mapWithKeys(fn (object $row) => [(int) $row->source_id => (int) $row->credit - (int) $row->debit]);

        return array_values(FixedAsset::query()
            ->where('fixed_assets.business_id', $business->id)
            ->whereNull('fixed_assets.cancelled_at')
            ->orderBy('fixed_assets.acquired_on')
            ->orderBy('fixed_assets.id')
            ->get()
            ->map(fn (FixedAsset $asset) => [
                'id' => $asset->id,
                'name' => $asset->name,
                'cost' => $asset->cost,
                'acquiredOn' => $asset->acquired_on->toDateString(),
                'lifeMonths' => $asset->useful_life_months,
                'accumulated' => $accumulated[$asset->id] ?? 0,
                'bookValue' => $asset->cost - ($accumulated[$asset->id] ?? 0),
                // Months already posted stay as they are; a new estimate would have to apply from here on.
                'locked' => $asset->depreciated_through !== null,
            ])
            ->all());
    }
}
