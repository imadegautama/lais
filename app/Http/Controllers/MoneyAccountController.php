<?php

namespace App\Http\Controllers;

use App\Enums\MoneyAccountKind;
use App\Http\Requests\MoneyAccountRequest;
use App\Http\Resources\MoneyAccountResource;
use App\Models\MoneyAccount;
use App\Services\Ledger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MoneyAccountController extends Controller
{
    /**
     * Kas & rekening: where the business's money is, with each balance.
     */
    public function index(Request $request, Ledger $ledger): Response
    {
        $business = $request->user()->currentBusiness();
        $balances = $ledger->moneyBalances($business);

        return Inertia::render('money-accounts/index', [
            // Balances are only right once the saldo awal is in.
            'needsOnboarding' => $business->onboarded_at === null,
            // Kas tunai first, then the rekening in the order they were added.
            'accounts' => $business->moneyAccounts()->orderByRaw('money_accounts.kind = ? desc', [MoneyAccountKind::Tunai->value])->orderBy('money_accounts.id')->get()
                ->map(fn (MoneyAccount $moneyAccount) => (new MoneyAccountResource($moneyAccount, $balances[$moneyAccount->id] ?? 0))->resolve())
                ->values()->all(),
        ]);
    }

    public function store(MoneyAccountRequest $request): RedirectResponse
    {
        $moneyAccount = $request->user()->currentBusiness()->moneyAccounts()->create([
            'kind' => MoneyAccountKind::NonTunai,
            'name' => $request->validated('name'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Rekening {$moneyAccount->name} ditambahkan."]);

        return back();
    }

    public function update(MoneyAccountRequest $request, MoneyAccount $moneyAccount): RedirectResponse
    {
        Gate::authorize('manage', $moneyAccount);

        $moneyAccount->update(['name' => $request->validated('name')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Nama diganti menjadi {$moneyAccount->name}."]);

        return back();
    }

    /**
     * Archive (soft delete). Its money has to be moved out first, so no balance
     * hides in a rekening nobody sees; Kas tunai and the last rekening stay,
     * because recording needs them.
     */
    public function destroy(Request $request, MoneyAccount $moneyAccount, Ledger $ledger): RedirectResponse
    {
        Gate::authorize('manage', $moneyAccount);

        $business = $moneyAccount->business;
        $balance = $ledger->moneyBalances($business)[$moneyAccount->id] ?? 0;
        $refusal = match (true) {
            $moneyAccount->kind === MoneyAccountKind::Tunai => 'Kas tunai tidak bisa diarsipkan.',
            $balance !== 0 => "Saldo {$moneyAccount->name} masih ada. Pindahkan dulu ke rekening lain lewat Pindah dana.",
            $business->moneyAccounts()->where('money_accounts.kind', MoneyAccountKind::NonTunai)->count() === 1 => 'Sisakan minimal satu rekening non-tunai. Ganti saja namanya.',
            default => null,
        };

        if ($refusal !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $refusal]);

            return back();
        }

        $moneyAccount->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$moneyAccount->name} diarsipkan."]);

        return back();
    }
}
