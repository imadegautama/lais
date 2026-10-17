<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\Period;
use App\Enums\TransactionSource;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\TransactionRequest;
use App\Http\Resources\ContactResource;
use App\Http\Resources\ItemOptionResource;
use App\Http\Resources\JournalEntryResource;
use App\Http\Resources\StockMovementResource;
use App\Http\Resources\TransactionDetailResource;
use App\Http\Resources\TransactionLineResource;
use App\Http\Resources\TransactionResource;
use App\Models\Contact;
use App\Models\Transaction;
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
                map: fn (Transaction $transaction) => TransactionResource::make($transaction)->resolve(),
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
            'items' => ItemOptionResource::collection($business->items()->with('recipeLines')->orderBy('items.name')->get())->resolve(),
            'contacts' => $business->contacts()->orderBy('contacts.name')->get()
                ->map(fn (Contact $contact) => (new ContactResource($contact, $balances[$contact->id] ?? 0))->resolve())
                ->values()->all(),
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
            'transaction' => TransactionDetailResource::make($transaction)->resolve(),
            'lines' => TransactionLineResource::collection($transaction->lines)->resolve(),
            'movements' => StockMovementResource::collection($transaction->stockMovements)->resolve(),
            'journal' => JournalEntryResource::collection($transaction->journalEntries)->resolve(),
        ]);
    }
}
