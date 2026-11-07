<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Business;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\MoneyAccount;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Double-entry journal. Every rupiah that moves goes through post(), so the
 * balance sheet always balances (PRD §4.4). Amounts are integer rupiah.
 * Lines on 1101/1102 also name their rekening (money_account_id), so each
 * rekening's balance is read from the same journal.
 */
class Ledger
{
    /**
     * @param  list<array{account: string, money_account_id?: int, debit?: int, credit?: int}>  $lines
     */
    public function post(Business $business, string $description, array $lines, ?Model $source = null, ?CarbonInterface $occurredAt = null): JournalEntry
    {
        $this->assertMoneyAccounts($business, $lines);

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

        // Own accounts of two businesses can share a code, so never look past this one.
        $accounts = Account::query()->visibleTo($business)->whereIn('accounts.code', array_column($lines, 'account'))->pluck('id', 'code');

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
                'money_account_id' => $line['money_account_id'] ?? null,
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
            ], $lines));

            return $entry;
        });
    }

    /**
     * Journal lines from signed amounts per account: positive is debit,
     * negative is credit, zero is dropped. Cash keys name their rekening
     * (cashKey()), so one entry can hold two rekening of the same account.
     *
     * @param  array<int|string, int>  $amounts  account code, or "code@rekening id" => rupiah
     * @return list<array{account: string, money_account_id?: int, debit?: int, credit?: int}>
     */
    public static function lines(array $amounts): array
    {
        $lines = [];

        foreach ($amounts as $key => $amount) {
            // PHP turns '1301' keys into ints, so the key is cast back to a string.
            [$account, $moneyAccountId] = array_pad(explode('@', (string) $key, 2), 2, null);
            $line = ['account' => $account] + ($moneyAccountId === null ? [] : ['money_account_id' => (int) $moneyAccountId]);

            if ($amount > 0) {
                $lines[] = $line + ['debit' => $amount];
            } elseif ($amount < 0) {
                $lines[] = $line + ['credit' => -$amount];
            }
        }

        return $lines;
    }

    /**
     * The amounts key for money in or out of a rekening: "1102@5".
     */
    public static function cashKey(MoneyAccount $moneyAccount): string
    {
        return $moneyAccount->kind->account().'@'.$moneyAccount->id;
    }

    /**
     * Signed business cash in a set of amounts (1101 + 1102), the
     * transaction's cash_flow. A Pindah dana nets to zero.
     *
     * @param  array<int|string, int>  $amounts
     */
    public static function cashTotal(array $amounts): int
    {
        $total = 0;

        foreach ($amounts as $key => $amount) {
            if (in_array(strtok((string) $key, '@'), [Account::CASH, Account::NON_CASH], true)) {
                $total += $amount;
            }
        }

        return $total;
    }

    /**
     * Balance per rekening, read from the journal. Without $excluding it sums
     * to 1101 + 1102; with it, the balance as if that transaction were cancelled.
     *
     * @return array<int, int> rekening id => rupiah
     */
    public function moneyBalances(Business $business, ?int $excluding = null): array
    {
        // Debit and credit are unsigned, so each side is summed before subtracting (MySQL rejects a negative unsigned).
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.business_id', $business->id)
            ->whereNotNull('journal_lines.money_account_id')
            ->when($excluding, fn ($query) => $query->whereNot(fn ($not) => $not
                ->where('journal_entries.source_type', (new Transaction)->getMorphClass())
                ->where('journal_entries.source_id', $excluding)))
            ->groupBy('journal_lines.money_account_id')
            ->selectRaw('journal_lines.money_account_id, sum(journal_lines.debit) as debit, sum(journal_lines.credit) as credit')
            ->get()
            ->mapWithKeys(fn (object $row) => [(int) $row->money_account_id => (int) $row->debit - (int) $row->credit])
            ->all();
    }

    /**
     * Cash lines must name a rekening of this business whose kind matches
     * the account (Kas tunai on 1101, bank or e-wallet on 1102); no other line may.
     *
     * @param  list<array{account: string, money_account_id?: int, debit?: int, credit?: int}>  $lines
     */
    private function assertMoneyAccounts(Business $business, array $lines): void
    {
        $ids = array_filter(array_column($lines, 'money_account_id'));
        $moneyAccounts = MoneyAccount::query()->withTrashed()->whereKey($ids)->get()->keyBy('id');

        foreach ($lines as $line) {
            $isCash = in_array($line['account'], [Account::CASH, Account::NON_CASH], true);
            $moneyAccount = isset($line['money_account_id']) ? $moneyAccounts->get($line['money_account_id']) : null;

            if ($isCash !== isset($line['money_account_id'])) {
                throw new InvalidArgumentException("Account {$line['account']} ".($isCash ? 'needs' : 'takes no').' rekening.');
            }

            if ($isCash && ($moneyAccount?->business_id !== $business->id || $moneyAccount->kind->account() !== $line['account'])) {
                throw new InvalidArgumentException('Rekening '.($line['money_account_id'] ?? '?')." does not belong on {$line['account']} of this business.");
            }
        }
    }

    /**
     * Open balance per contact: what a customer still owes (Piutang 1201) or
     * what is still owed to a supplier (Utang 2101). Read from the journal, so
     * the sum over contacts always equals the account balance. $excluding
     * leaves one transaction out: the balance as if it were cancelled.
     *
     * @return array<int, int> contact id => rupiah
     */
    public function contactBalances(Business $business, ?int $contactId = null, ?int $excluding = null): array
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
            ->when($excluding, fn ($query) => $query->where('transactions.id', '<>', $excluding))
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
