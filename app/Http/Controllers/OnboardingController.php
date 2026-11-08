<?php

namespace App\Http\Controllers;

use App\Enums\BusinessCategory;
use App\Enums\ItemKind;
use App\Http\Requests\OnboardingRequest;
use App\Models\Account;
use App\Services\Onboarding;
use App\Support\StarterCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The onboarding wizard (UI-PAGES B5). Skippable: until it is done, the
 * dashboard and Kas & rekening remind the owner to finish it.
 */
class OnboardingController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $business = $request->user()->currentBusiness();

        // Opened once; corrections go through the saldo awal records themselves.
        if ($business->onboarded_at !== null) {
            return to_route('dashboard');
        }

        $stocked = $business->items()->whereIn('items.kind', ItemKind::stocked());

        return Inertia::render('onboarding/index', [
            'business' => ['name' => $business->name, 'category' => $business->category],
            'draft' => $business->onboarding_draft,
            'accounts' => $business->moneyAccounts()->orderBy('money_accounts.id')->get(['id', 'kind', 'name']),
            'latestStart' => Onboarding::latestStart($business)->toDateString(),
            'hasRecords' => $business->transactions()->exists(),
            'templates' => collect(BusinessCategory::cases())->mapWithKeys(fn (BusinessCategory $category) => [$category->value => StarterCatalog::for($category)]),
            // Products added before the wizard already hold opening stock; the summary counts it.
            'inventory' => ['count' => (clone $stocked)->count(), 'value' => (int) (clone $stocked)->sum('items.stock_value')],
            // The fixed chart (COA) the "Akun" step shows, group by group.
            'chart' => Account::query()->whereNull('accounts.business_id')->orderBy('accounts.code')->get(['code', 'name', 'type']),
        ]);
    }

    /**
     * Save the draft after a step, so the wizard can be picked up later.
     */
    public function update(OnboardingRequest $request): RedirectResponse
    {
        // Kept as typed (text quantities, "contoh" markers), so the wizard resumes exactly where it was.
        $request->user()->currentBusiness()->update([
            'onboarding_draft' => Arr::only((array) $request->input('draft', []), ['step', 'name', 'category', 'started_on', 'accounts', 'equipment', 'products', 'debts', 'receivables', 'custom_accounts']),
        ]);

        return back();
    }

    public function store(OnboardingRequest $request, Onboarding $onboarding): RedirectResponse
    {
        $onboarding->complete($request->user()->currentBusiness(), $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pembukuan sudah dibuka.',
            'description' => 'Saldo awal tersimpan. Mulai catat penjualan hari ini.',
        ]);

        return to_route('dashboard', ['tur' => 1]);
    }
}
