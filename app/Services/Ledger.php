<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Business;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Transaction;
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
     * Journal lines from signed amounts per account: positive is debit,
     * negative is credit, zero is dropped.
     *
     * @param  array<int|string, int>  $amounts  account code => rupiah
     * @return list<array{account: string, debit?: int, credit?: int}>
     */
    public static function lines(array $amounts): array
    {
        $lines = [];

        foreach ($amounts as $account => $amount) {
            // PHP turns '1301' keys into ints, so the code is cast back to a string.
            if ($amount > 0) {
                $lines[] = ['account' => (string) $account, 'debit' => $amount];
            } elseif ($amount < 0) {
                $lines[] = ['account' => (string) $account, 'credit' => -$amount];
            }
        }

        return $lines;
    }

    /**
     * Open balance per contact: what a customer still owes (Piutang 1201) or
     * what is still owed to a supplier (Utang 2101). Read from the journal, so
     * the sum over contacts always equals the account balance.
     *
     * @return array<int, int> contact id => rupiah
     */
    public function contactBalances(Business $business, ?int $contactId = null): array
    {
        // Debit and credit are unsigned, so each side is summed before subtracting (MySQL rejects a negative unsigned).
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('transactions', function ($join) {
                $join->on('transactions.id', '=', 'journal_entries.source_id')
                    ->where('journal_entries.source_type', (new Transaction)->getMorphClass());
            })
            ->where('journal_entries.business_id', $business->id)
            ->whereIn('accounts.code', [Account::RECEIVABLE, Account::PAYABLE])
            ->whereNotNull('transactions.contact_id')
            ->when($contactId, fn ($query) => $query->where('transactions.contact_id', $contactId))
            ->groupBy('transactions.contact_id')
            ->selectRaw('transactions.contact_id, sum(case when accounts.code = ? then journal_lines.debit else journal_lines.credit end) as raised, sum(case when accounts.code = ? then journal_lines.credit else journal_lines.debit end) as settled', [Account::RECEIVABLE, Account::RECEIVABLE])
            ->get()
            ->mapWithKeys(fn (object $row) => [(int) $row->contact_id => (int) $row->raised - (int) $row->settled])
            ->all();
    }

    /**
     * Balance of one account on its normal side (debit accounts: debit − credit).
     */
    public function balance(Business $business, string $code): int
    {
        $account = Account::query()->where('code', $code)->firstOrFail();

        $totals = JournalLine::query()
            ->where('journal_lines.account_id', $account->id)
            ->whereHas('entry', fn ($query) => $query->where('journal_entries.business_id', $business->id))
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as debit, COALESCE(SUM(journal_lines.credit), 0) as credit')
            ->first();

        $net = (int) $totals->debit - (int) $totals->credit;

        return $account->normal_balance === 'debit' ? $net : -$net;
    }
}
