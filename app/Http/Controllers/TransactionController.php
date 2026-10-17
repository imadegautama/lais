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
use App\Http\Resources\TransactionFormResource;
use App\Http\Resources\TransactionLineResource;
use App\Http\Resources\TransactionResource;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Item;
use App\Models\StockMovement;
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
     */
    public function create(Request $request, Ledger $ledger): Response
    {
        $business = $request->user()->currentBusiness();

        return Inertia::render('transactions/create', [
            'initialType' => TransactionType::tryFrom((string) $request->query('jenis')) ?? TransactionType::Penjualan,
            ...$this->formOptions($business, $ledger),
        ]);
    }

    /**
     * "Ubah" (UI-PAGES C4): the record form filled in. Nothing is cancelled
     * until it is saved, so leaving the page changes nothing.
     */
    public function edit(Request $request, Transaction $transaction, Ledger $ledger): Response|RedirectResponse
    {
        Gate::authorize('manage', $transaction);

        $transaction->load(['lines.item', 'stockMovements.item']);
        $archived = $transaction->lines->pluck('item')->merge($transaction->stockMovements->pluck('item'))
            ->filter(fn (Item $item) => $item->trashed())->pluck('name')->unique();

        if ($transaction->isVoid() || $archived->isNotEmpty()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $transaction->isVoid()
                    ? 'Catatan ini sudah dibatalkan.'
                    : "{$archived->join(', ', ' dan ')} sudah diarsipkan, jadi catatan ini tidak bisa diubah.",
            ]);

            return to_route('transactions.show', $transaction);
        }

        return Inertia::render('transactions/create', [
            'initialType' => $transaction->type,
            'editing' => ['id' => $transaction->id, 'description' => $transaction->description],
            'initial' => TransactionFormResource::make($transaction)->resolve(),
            ...$this->formOptions($request->user()->currentBusiness(), $ledger, $transaction),
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

    public function update(TransactionRequest $request, Transaction $transaction, TransactionService $service): RedirectResponse
    {
        Gate::authorize('manage', $transaction);

        $replacement = $service->replace($transaction, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Perubahan disimpan.',
            'description' => $replacement->description,
        ]);

        return to_route('transactions.show', $replacement);
    }

    public function cancel(Request $request, Transaction $transaction, TransactionService $service): RedirectResponse
    {
        Gate::authorize('manage', $transaction);

        $validated = $request->validate(
            ['reason' => ['nullable', 'string', 'max:200']],
            ['reason.max' => 'Alasannya paling panjang 200 huruf.'],
        );

        $service->cancel($transaction, $validated['reason'] ?? null);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Catatan dibatalkan.',
            'description' => 'Stok, kas, dan utang-piutangnya sudah dikembalikan.',
        ]);

        return to_route('transactions.show', $transaction);
    }

    /**
     * Transaction detail (UI-PAGES C4): lines, stock changes, and the journal.
     */
    public function show(Request $request, Transaction $transaction): Response
    {
        Gate::authorize('manage', $transaction);

        $transaction->load(['contact', 'replaces:id,replaced_by_id', 'lines.item', 'stockMovements.item', 'journalEntries.lines.account']);

        return Inertia::render('transactions/show', [
            'transaction' => TransactionDetailResource::make($transaction)->resolve(),
            'lines' => TransactionLineResource::collection($transaction->lines)->resolve(),
            'movements' => StockMovementResource::collection($transaction->stockMovements)->resolve(),
            'journal' => JournalEntryResource::collection($transaction->journalEntries)->resolve(),
        ]);
    }

    /**
     * Items and contacts for the record form. When editing, stock and balances
     * read as if $editing were already cancelled, so the impact preview adds up.
     * shortcut: sends every item and contact, fine for a few hundred; search on the server beyond that.
     *
     * @return array{now: string, items: array<mixed>, contacts: array<mixed>}
     */
    private function formOptions(Business $business, Ledger $ledger, ?Transaction $editing = null): array
    {
        $balances = $ledger->contactBalances($business, excluding: $editing?->id);
        $movements = $editing?->stockMovements->groupBy('item_id') ?? collect();
        $items = $business->items()->with('recipeLines')->orderBy('items.name')->get()
            ->each(function (Item $item) use ($movements) {
                if ($moved = $movements->get($item->id)) {
                    // Exact unless units moved since; only for the preview, never saved.
                    $item->stock = (string) round((float) $item->stock - $moved->sum(fn (StockMovement $movement) => (float) $movement->qty), 3);
                    $item->stock_value -= (int) $moved->sum('value');
                }
            });

        return [
            'now' => now()->format('Y-m-d\TH:i'),
            'items' => ItemOptionResource::collection($items)->resolve(),
            'contacts' => $business->contacts()->orderBy('contacts.name')->get()
                ->map(fn (Contact $contact) => (new ContactResource($contact, $balances[$contact->id] ?? 0))->resolve())
                ->values()->all(),
        ];
    }
}
