<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Http\Requests\AccountRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * The accounts a business adds to its chart. The fixed accounts are locked;
 * an account in use is archived, never deleted, so its journal keeps its name.
 */
class AccountController extends Controller
{
    public function store(AccountRequest $request): RedirectResponse
    {
        $business = $request->user()->currentBusiness();
        $type = AccountType::from((string) $request->validated('type'));
        $account = new Account([
            'code' => Account::nextCode($business, $type),
            'name' => $request->validated('name'),
            'type' => $type,
            'normal_balance' => $type === AccountType::Beban ? 'debit' : 'credit',
        ]);
        $account->business_id = $business->id;
        $account->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Akun {$account->name} ditambahkan dengan kode {$account->code}."]);

        return back();
    }

    public function update(AccountRequest $request, Account $account): RedirectResponse
    {
        Gate::authorize('manage', $account);

        $account->update(['name' => $request->validated('name')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Nama diganti menjadi {$account->name}."]);

        return back();
    }

    /**
     * Archive or bring back: an archived account stays in the reports but
     * cannot be chosen for a new record.
     */
    public function archive(Account $account): RedirectResponse
    {
        Gate::authorize('manage', $account);

        $archived = $account->archived_at === null;
        $account->update(['archived_at' => $archived ? now() : null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $archived ? "{$account->name} diarsipkan." : "{$account->name} aktif lagi."]);

        return back();
    }
}
