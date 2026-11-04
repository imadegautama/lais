<?php

namespace App\Services;

use App\Enums\ContactType;
use App\Enums\PaymentMethod;
use App\Enums\TransactionKind;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Utang-piutang (PRD M5): what each customer still owes and what the
 * business owes each supplier, bill by bill. The one place that says how a
 * payment settles bills, so the debts page, the dashboard and an order's
 * paid amount always agree:
 *
 * - a bill is a posted transaction that raises Piutang 1201 or Utang 2101
 *   (credit sale, an order's sale, credit purchase, opening balance);
 * - a payment made from an order settles that order's bill first;
 * - everything else settles the oldest open bill first.
 *
 * @phpstan-type Bill array{transactionId: int, occurredAt: CarbonImmutable, description: string, orderId: int|null, orderCode: string|null, amount: int, remaining: int}
 * @phpstan-type Payment array{transactionId: int, occurredAt: CarbonImmutable, description: string, orderId: int|null, orderCode: string|null, amount: int, method: string, moneyAccount: string|null, hasProof: bool}
 * @phpstan-type Book array{bills: list<Bill>, payments: list<Payment>, balance: int}
 */
class Debts
{
    public function __construct(private TransactionService $transactions) {}

    /**
     * Bills and payments per contact of one side (customers or suppliers).
     * Cancelled records and their mirror journal both drop out.
     *
     * @return array<int, Book> contact id => its book
     */
    public function books(Business $business, ContactType $type, ?int $contactId = null): array
    {
        $receivable = $type === ContactType::Pelanggan;
        $rows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('transactions', function ($join) {
                $join->on('transactions.id', '=', 'journal_entries.source_id')
                    ->where('journal_entries.source_type', (new Transaction)->getMorphClass());
            })
            ->leftJoin('orders', 'orders.id', '=', 'transactions.order_id')
            ->leftJoin('money_accounts', 'money_accounts.id', '=', 'transactions.money_account_id')
            ->where('journal_entries.business_id', $business->id)
            ->where('accounts.code', $receivable ? Account::RECEIVABLE : Account::PAYABLE)
            ->where('transactions.status', TransactionStatus::Posted->value)
            ->whereNotNull('transactions.contact_id')
            ->when($contactId, fn ($query) => $query->where('transactions.contact_id', $contactId))
            ->groupBy('transactions.id', 'transactions.contact_id', 'transactions.order_id', 'orders.code', 'transactions.occurred_at', 'transactions.description', 'transactions.method', 'transactions.proof_path', 'money_accounts.name')
            ->orderBy('transactions.occurred_at')
            ->orderBy('transactions.id')
            // Debit and credit are unsigned, so each side is summed before subtracting (MySQL rejects a negative unsigned).
            ->selectRaw('transactions.id, transactions.contact_id, transactions.order_id, orders.code as order_code, transactions.occurred_at, transactions.description, transactions.method, transactions.proof_path, money_accounts.name as money_account, sum(journal_lines.debit) as debit, sum(journal_lines.credit) as credit')
            ->get();

        $books = [];

        foreach ($rows->groupBy('contact_id') as $id => $contactRows) {
            $bills = [];
            $payments = [];

            foreach ($contactRows as $row) {
                // Piutang grows on the debit side, utang on the credit side.
                $net = $receivable ? (int) $row->debit - (int) $row->credit : (int) $row->credit - (int) $row->debit;
                $entry = [
                    'transactionId' => (int) $row->id,
                    'occurredAt' => CarbonImmutable::parse($row->occurred_at),
                    'description' => (string) $row->description,
                    'orderId' => $row->order_id === null ? null : (int) $row->order_id,
                    'orderCode' => $row->order_code,
                ];

                if ($net > 0) {
                    $bills[] = $entry + ['amount' => $net, 'remaining' => $net];
                } elseif ($net < 0) {
                    $payments[] = $entry + ['amount' => -$net, 'method' => (string) $row->method, 'moneyAccount' => $row->money_account, 'hasProof' => $row->proof_path !== null];
                }
            }

            $books[(int) $id] = self::settle($bills, $payments);
        }

        return $books;
    }

    /**
     * Who still owes (or is owed), oldest open bill first.
     *
     * @return list<array{contact: Contact, balance: int, since: CarbonImmutable, open: int}>
     */
    public function summary(Business $business, ContactType $type): array
    {
        $open = [];

        foreach ($this->books($business, $type) as $contactId => $book) {
            $unpaid = array_values(array_filter($book['bills'], fn (array $bill) => $bill['remaining'] > 0));

            if ($unpaid !== []) {
                $open[$contactId] = ['balance' => $book['balance'], 'since' => $unpaid[0]['occurredAt'], 'open' => count($unpaid)];
            }
        }

        $rows = Contact::query()->whereKey(array_keys($open))->get()
            ->map(fn (Contact $contact) => ['contact' => $contact] + $open[$contact->id])
            ->sortBy(fn (array $row) => $row['since']->getTimestamp())
            ->all();

        return array_values($rows);
    }

    /**
     * What is still unpaid on one transaction (an order's sale), after the
     * contact's payments are applied. Zero when it raised no bill.
     */
    public function remaining(Transaction $transaction): int
    {
        if ($transaction->contact_id === null) {
            return 0;
        }

        $book = $this->books($transaction->business, $transaction->contact->type, $transaction->contact_id)[$transaction->contact_id] ?? null;

        foreach ($book['bills'] ?? [] as $bill) {
            if ($bill['transactionId'] === $transaction->id) {
                return $bill['remaining'];
            }
        }

        return 0;
    }

    /**
     * Money a customer paid, or the business paid a supplier, from the
     * debts page. TransactionService checks it against the open balance.
     */
    public function pay(Contact $contact, int $amount, PaymentMethod $method, ?int $moneyAccountId = null): Transaction
    {
        return $this->transactions->record($contact->business, [
            'type' => TransactionType::UtangPiutang->value,
            'kind' => ($contact->type === ContactType::Pelanggan ? TransactionKind::TerimaPiutang : TransactionKind::BayarUtang)->value,
            'method' => $method->value,
            'money_account_id' => $moneyAccountId,
            'amount' => $amount,
            'contact_id' => $contact->id,
        ]);
    }

    /**
     * Payments from an order go to that order's bill; the rest, and anything
     * left over, settles the oldest open bills first.
     *
     * @param  list<Bill>  $bills  oldest first
     * @param  list<Payment>  $payments
     * @return Book
     */
    private static function settle(array $bills, array $payments): array
    {
        $pool = 0;

        foreach ($payments as $payment) {
            $left = $payment['amount'];

            foreach ($bills as $index => $bill) {
                if ($payment['orderId'] !== null && $bill['orderId'] === $payment['orderId']) {
                    $take = min($left, $bill['remaining']);
                    $bills[$index]['remaining'] -= $take;
                    $left -= $take;
                }
            }

            $pool += $left;
        }

        foreach ($bills as $index => $bill) {
            $take = min($pool, $bill['remaining']);
            $bills[$index]['remaining'] -= $take;
            $pool -= $take;
        }

        return ['bills' => $bills, 'payments' => $payments, 'balance' => array_sum(array_column($bills, 'remaining'))];
    }
}
