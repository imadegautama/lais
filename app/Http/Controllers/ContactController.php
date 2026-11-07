<?php

namespace App\Http\Controllers;

use App\Enums\ContactType;
use App\Enums\TransactionStatus;
use App\Http\Requests\ContactRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Contact;
use App\Models\Order;
use App\Services\Contacts;
use App\Services\Ledger;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kontak (UI-PAGES C12): who the business deals with. Bills and payments stay
 * in Utang-piutang; this is the person, their history, and their data.
 */
class ContactController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $business = $request->user()->currentBusiness();
        $asked = $request->query('jenis');
        $tab = ContactType::tryFrom((string) (is_array($asked) ? ($asked[0] ?? '') : $asked)) ?? ContactType::Pelanggan;
        // A filter, not a plain parameter, so sorting and paging keep the tab.
        $request->query->set('jenis', [$tab->value]);
        $balances = $ledger->contactBalances($business);
        $posted = fn (Builder $query) => $query->where('transactions.status', TransactionStatus::Posted);

        return Inertia::render('contacts/index', [
            'tab' => $tab,
            'counts' => [
                'pelanggan' => $business->contacts()->where('contacts.type', ContactType::Pelanggan)->count(),
                'supplier' => $business->contacts()->where('contacts.type', ContactType::Supplier)->count(),
            ],
            'contacts' => TableQuery::paginate(
                $request,
                $business->contacts()->getQuery()->withCount(['transactions' => $posted])->withMax(['transactions' => $posted], 'occurred_at'),
                map: fn (Contact $contact) => [
                    ...self::person($contact),
                    'transactions' => (int) $contact->getAttribute('transactions_count'),
                    'lastAt' => $contact->getAttribute('transactions_max_occurred_at'),
                    'balance' => $balances[$contact->id] ?? 0,
                ],
                sorts: [
                    'nama' => 'name',
                    // Aggregates of this query, so they take no table name.
                    'transaksi' => fn (Builder $query, string $direction) => $query->orderBy('transactions_count', $direction),
                    'terakhir' => fn (Builder $query, string $direction) => $query->orderBy('transactions_max_occurred_at', $direction),
                ],
                defaultSort: 'nama',
                filters: ['jenis' => ['contacts.type', ContactType::values()]],
                search: ['contacts.name', 'contacts.phone'],
            ),
        ]);
    }

    public function show(Contact $contact, Ledger $ledger, Contacts $contacts): Response
    {
        Gate::authorize('manage', $contact);
        abort_if($contact->erased_at !== null, 404);

        return Inertia::render('contacts/show', [
            'contact' => self::person($contact),
            'balance' => $ledger->contactBalances($contact->business, $contact->id)[$contact->id] ?? 0,
            'blockedBy' => $contacts->blockedBy($contact),
            'transactions' => TransactionResource::collection(
                $contact->transactions()->with(['contact', 'moneyAccount', 'toMoneyAccount'])->latest('occurred_at')->latest('id')->limit(200)->get(),
            )->resolve(),
            'orders' => Order::query()->where('contact_id', $contact->id)->latest('id')->limit(200)->get()
                ->map(fn (Order $order) => ['code' => $order->code, 'status' => $order->status, 'total' => $order->total, 'createdAt' => $order->created_at->toIso8601String()]),
        ]);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $contact = $request->user()->currentBusiness()->contacts()->create($request->person());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$contact->name} ditambahkan ke kontak."]);

        return back();
    }

    public function update(ContactRequest $request, Contact $contact): RedirectResponse
    {
        Gate::authorize('manage', $contact);

        $contact->update($request->person());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kontak diperbarui.']);

        return back();
    }

    public function destroy(Contact $contact, Contacts $contacts): RedirectResponse
    {
        Gate::authorize('manage', $contact);

        $blocked = $contacts->blockedBy($contact);

        if ($blocked !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $blocked]);

            return back();
        }

        $type = $contact->type;
        $contacts->erase($contact);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data kontak dihapus.']);

        return to_route('contacts.index', ['jenis' => $type->value]);
    }

    /**
     * @return array{id: int, name: string, type: ContactType, phone: string|null}
     */
    private static function person(Contact $contact): array
    {
        return ['id' => $contact->id, 'name' => $contact->name, 'type' => $contact->type, 'phone' => $contact->phone];
    }
}
