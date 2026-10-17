<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\Period;
use App\Enums\TransactionSource;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\TransactionRequest;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\RecipeLine;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Services\Ledger;
use App\Services\TransactionService;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /**
     * Transaction list (UI-PAGES C2), filtered and paginated on the server.
     */
    public function index(Request $request): Response
    {
        $business = $request->user()->currentBusiness();

        return Inertia::render('transactions/index', [
            'transactions' => TableQuery::paginate(
                $request,
                $business->transactions()->getQuery()->with('contact'),
                map: fn (Transaction $transaction) => $this->row($transaction),
                sorts: ['waktu' => 'occurred_at', 'nominal' => 'total'],
                defaultSort: '-waktu',
                filters: [
                    'periode' => [
                        fn (Builder $query, array $values) => $query->where('transactions.occurred_at', '>=', Period::from($values[0])->start()),
                        Period::values(),
                    ],
                    'jenis' => ['type', TransactionType::values()],
                    'metode' => ['method', PaymentMethod::values()],
                    'sumber' => ['source', TransactionSource::values()],
                ],
                search: ['description', 'note', '(select contacts.name from contacts where contacts.id = transactions.contact_id)'],
                summary: fn (Builder $query) => [
                    'cashIn' => (int) (clone $query)->where('transactions.status', TransactionStatus::Posted)->where('transactions.cash_flow', '>', 0)->sum('transactions.cash_flow'),
                    'cashOut' => -(int) (clone $query)->where('transactions.status', TransactionStatus::Posted)->where('transactions.cash_flow', '<', 0)->sum('transactions.cash_flow'),
                    'count' => (clone $query)->count(),
                ],
            ),
            'hasTransactions' => fn () => $business->transactions()->exists(),
        ]);
    }

    /**
     * Record form (UI-PAGES C3). `?jenis=` opens the matching tab.
     * shortcut: sends every item and contact, fine for a few hundred; search on the server beyond that.
     */
    public function create(Request $request, Ledger $ledger): Response
    {
        $business = $request->user()->currentBusiness();
        $balances = $ledger->contactBalances($business);

        return Inertia::render('transactions/create', [
            'initialType' => TransactionType::tryFrom((string) $request->query('jenis')) ?? TransactionType::Penjualan,
            'now' => now()->format('Y-m-d\TH:i'),
            'items' => $business->items()->with('recipeLines')->orderBy('name')->get()->map(fn (Item $item) => [
                'id' => $item->id,
                'kind' => $item->kind,
                'name' => $item->name,
                'aliases' => $item->aliases ?? [],
                'unit' => $item->unit,
                'price' => $item->price,
                'stock' => (float) $item->stock,
                'averageCost' => round($item->averageCost(), 4),
                'recipe' => $item->recipeLines->map(fn (RecipeLine $line) => [
                    'materialId' => $line->material_id,
                    'qty' => (float) $line->qty,
                ])->values()->all(),
            ])->values()->all(),
            'contacts' => $business->contacts()->orderBy('name')->get()->map(fn (Contact $contact) => [
                'id' => $contact->id,
                'type' => $contact->type,
                'name' => $contact->name,
                'balance' => $balances[$contact->id] ?? 0,
            ])->values()->all(),
        ]);
    }

    public function store(TransactionRequest $request, TransactionService $service): RedirectResponse
    {
        $transaction = $service->record($request->user()->currentBusiness(), $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $transaction->type->savedMessage(),
            'description' => $transaction->description,
            'action' => ['label' => 'Lihat', 'href' => route('transactions.show', $transaction)],
        ]);

        return $request->boolean('again')
            ? to_route('transactions.create', ['jenis' => $transaction->type->value])
            : to_route('transactions.index');
    }

    /**
     * Transaction detail (UI-PAGES C4): lines, stock changes, and the journal.
     */
    public function show(Request $request, Transaction $transaction): Response
    {
        Gate::authorize('manage', $transaction);

        $transaction->load(['contact', 'lines.item', 'stockMovements.item', 'journalEntries.lines.account']);

        return Inertia::render('transactions/show', [
            'transaction' => $this->row($transaction) + [
                'kind' => $transaction->kind,
                'note' => $transaction->note,
                'paidAmount' => $transaction->paid_amount,
                'cashFlow' => $transaction->cash_flow,
                'voidedAt' => $transaction->voided_at?->toIso8601String(),
                'voidReason' => $transaction->void_reason,
            ],
            'lines' => $transaction->lines->map(fn (TransactionLine $line) => [
                'id' => $line->id,
                'name' => $line->item->name,
                'unit' => $line->item->unit,
                'qty' => (float) $line->qty,
                'price' => $line->price,
                'subtotal' => $line->subtotal,
                'hpp' => $line->hpp,
            ])->values()->all(),
            'movements' => $transaction->stockMovements->map(fn (StockMovement $movement) => [
                'id' => $movement->id,
                'name' => $movement->item->name,
                'unit' => $movement->item->unit,
                'qty' => (float) $movement->qty,
                'value' => $movement->value,
                'stockAfter' => $movement->stock_after === null ? null : (float) $movement->stock_after,
                'reason' => $movement->reason,
            ])->values()->all(),
            'journal' => $transaction->journalEntries->map(fn (JournalEntry $entry) => [
                'id' => $entry->id,
                'description' => $entry->description,
                'occurredAt' => $entry->occurred_at->toIso8601String(),
                'lines' => $entry->lines->map(fn (JournalLine $line) => [
                    'code' => $line->account->code,
                    'account' => $line->account->name,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                ])->values()->all(),
            ])->values()->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'occurredAt' => $transaction->occurred_at->toIso8601String(),
            'type' => $transaction->type,
            'description' => $transaction->description,
            'method' => $transaction->method,
            'source' => $transaction->source,
            'amount' => $transaction->total,
            // Credit and personal-money records move no business cash, so they carry no +/− sign.
            'direction' => $transaction->method === PaymentMethod::Kredit || $transaction->cash_flow === 0
                ? 'none'
                : ($transaction->cash_flow > 0 ? 'in' : 'out'),
            'contact' => $transaction->contact?->name,
            'voided' => $transaction->isVoid(),
        ];
    }
}
