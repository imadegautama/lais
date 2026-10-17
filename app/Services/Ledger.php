<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Business;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Double-entry journal. Every rupiah that moves goes through post(), so the
 * balance sheet always balances (PRD §4.4). Amounts are integer rupiah.
 */
class Ledger
{
    /**
     * @param  list<array{account: string, debit?: int, credit?: int}>  $lines
     */
    public function post(Business $business, string $description, array $lines, ?Model $source = null, ?CarbonInterface $occurredAt = null): JournalEntry
    {
        $debit = 0;
        $credit = 0;

        foreach ($lines as $line) {
            $lineDebit = $line['debit'] ?? 0;
            $lineCredit = $line['credit'] ?? 0;

            if ($lineDebit < 0 || $lineCredit < 0 || ($lineDebit > 0) === ($lineCredit > 0)) {
                throw new InvalidArgumentException('Each journal line must have exactly one positive side.');
            }

            $debit += $lineDebit;
            $credit += $lineCredit;
        }

        if ($debit === 0 || $debit !== $credit) {
            throw new InvalidArgumentException("Unbalanced journal: debit {$debit}, credit {$credit}.");
        }

        $accounts = Account::query()->whereIn('code', array_column($lines, 'account'))->pluck('id', 'code');

        return DB::transaction(function () use ($business, $description, $lines, $source, $occurredAt, $accounts) {
            $entry = JournalEntry::query()->create([
                'business_id' => $business->id,
                'occurred_at' => $occurredAt ?? now(),
                'description' => $description,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
            ]);

            $entry->lines()->createMany(array_map(fn (array $line) => [
                'account_id' => $accounts[$line['account']] ?? throw new InvalidArgumentException("Unknown account {$line['account']}."),
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
            ], $lines));

            return $entry;
        });
    }

    /**
     * Balance of one account on its normal side (debit accounts: debit − credit).
     */
    public function balance(Business $business, string $code): int
    {
        $account = Account::query()->where('code', $code)->firstOrFail();

        $totals = JournalLine::query()
            ->where('account_id', $account->id)
            ->whereHas('entry', fn ($query) => $query->where('business_id', $business->id))
            ->selectRaw('COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')
            ->first();

        $net = (int) $totals->debit - (int) $totals->credit;

        return $account->normal_balance === 'debit' ? $net : -$net;
    }
}
