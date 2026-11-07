<?php

namespace App\Http\Controllers;

use App\Enums\ContactType;
use App\Enums\PaymentMethod;
use App\Http\Requests\PaymentRequest;
use App\Http\Resources\MoneyAccountResource;
use App\Models\Business;
use App\Models\Contact;
use App\Services\Debts;
use App\Services\Ledger;
use App\Support\Proof;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Utang-piutang (UI-PAGES C11): who still owes the business and whom it
 * owes, each contact's bills, and payments against them. Every amount comes
 * from Debts, so it matches the dashboard and the orders.
 */
class DebtController extends Controller
{
    public function index(Request $request, Debts $debts, Ledger $ledger): Response
    {
        $business = $request->user()->currentBusiness();
        $tab = $request->query('jenis') === 'utang' ? 'utang' : 'piutang';
        $sides = [
            'piutang' => $debts->summary($business, ContactType::Pelanggan),
            'utang' => $debts->summary($business, ContactType::Supplier),
        ];

        return Inertia::render('debts/index', [
            'tab' => $tab,
            'totals' => array_map(fn (array $rows) => [
                'balance' => array_sum(array_column($rows, 'balance')),
                'contacts' => count($rows),
                // Rows are oldest first.
                'oldestDays' => $rows === [] ? null : self::age($rows[0]['since']),
            ], $sides),
            'rows' => array_map(fn (array $row) => [
                ...self::contact($row['contact']),
                'balance' => $row['balance'],
                'since' => $row['since']->toIso8601String(),
                'ageDays' => self::age($row['since']),
                'open' => $row['open'],
            ], $sides[$tab]),
            ...self::paying($business, $ledger),
        ]);
    }

    /**
     * One contact's bills, open ones first, and the payments against them.
     */
    public function show(Request $request, Contact $contact, Debts $debts, Ledger $ledger): Response
    {
        Gate::authorize('manage', $contact);

        $book = $debts->books($contact->business, $contact->type, $contact->id)[$contact->id] ?? ['bills' => [], 'payments' => [], 'balance' => 0];
        $bills = collect($book['bills'])
            ->sortBy(fn (array $bill) => [$bill['remaining'] === 0 ? 1 : 0, $bill['remaining'] === 0 ? -$bill['occurredAt']->getTimestamp() : $bill['occurredAt']->getTimestamp()])
            ->values();

        return Inertia::render('debts/show', [
            'contact' => self::contact($contact),
            'balance' => $book['balance'],
            'bills' => $bills->map(fn (array $bill) => [
                'transactionId' => $bill['transactionId'],
                'occurredAt' => $bill['occurredAt']->toIso8601String(),
                'description' => $bill['description'],
                'orderCode' => $bill['orderCode'],
                'amount' => $bill['amount'],
                'remaining' => $bill['remaining'],
                'ageDays' => self::age($bill['occurredAt']),
            ]),
            'payments' => collect($book['payments'])->reverse()->values()->map(fn (array $payment) => [
                'transactionId' => $payment['transactionId'],
                'occurredAt' => $payment['occurredAt']->toIso8601String(),
                'orderCode' => $payment['orderCode'],
                'method' => $payment['method'],
                'moneyAccount' => $payment['moneyAccount'],
                'amount' => $payment['amount'],
                'hasProof' => $payment['hasProof'],
            ]),
            ...self::paying($contact->business, $ledger),
        ]);
    }

    public function pay(PaymentRequest $request, Contact $contact, Debts $debts): RedirectResponse
    {
        Gate::authorize('manage', $contact);

        $data = $request->validated();
        $payment = $debts->pay($contact, (int) $data['amount'], PaymentMethod::from($data['method']), $data['money_account_id'] ?? null);

        if ($request->hasFile('proof')) {
            Proof::attach($payment, $request->file('proof'));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $contact->type === ContactType::Pelanggan
            ? "Bayaran dari {$contact->name} dicatat."
            : "Pembayaran ke {$contact->name} dicatat."]);

        return back();
    }

    /**
     * @return array{id: int, name: string, type: ContactType, phone: string|null}
     */
    private static function contact(Contact $contact): array
    {
        return ['id' => $contact->id, 'name' => $contact->name, 'type' => $contact->type, 'phone' => $contact->phone];
    }

    /**
     * What the payment dialog and the reminder need.
     *
     * @return array{moneyAccounts: array<int, array<string, mixed>>, businessName: string}
     */
    private static function paying(Business $business, Ledger $ledger): array
    {
        return ['moneyAccounts' => MoneyAccountResource::options($business, $ledger), 'businessName' => $business->name];
    }

    /** Whole days since the bill, counted on the calendar. */
    private static function age(CarbonImmutable $since): int
    {
        return (int) $since->startOfDay()->diffInDays(now()->startOfDay());
    }
}
