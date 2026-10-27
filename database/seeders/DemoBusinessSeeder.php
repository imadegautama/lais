<?php

namespace Database\Seeders;

use App\Enums\BusinessCategory;
use App\Enums\MoneyAccountKind;
use App\Models\Business;
use App\Models\User;
use App\Services\Catalog;
use App\Services\TransactionService;
use App\Support\StarterCatalog;
use Illuminate\Database\Seeder;

/**
 * Example business "Warung Bu Rina" for the demo and the video (PRD §2.6).
 * Safe to run more than once: it skips when the business already has items.
 */
class DemoBusinessSeeder extends Seeder
{
    public function run(Catalog $catalog, TransactionService $transactions): void
    {
        $user = User::query()->where('email', 'demo@lais.test')->first() ?? User::query()->first();

        if (! $user) {
            return;
        }

        $business = Business::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['name' => 'Warung Bu Rina', 'slug' => 'warung-bu-rina'],
        );

        if ($business->items()->exists()) {
            return;
        }

        // The rekening a warung typically has: its bank and an e-wallet for QRIS.
        $business->moneyAccounts()->where('kind', MoneyAccountKind::NonTunai)->first()?->update(['name' => 'BCA']);
        $business->moneyAccounts()->firstOrCreate(['name' => 'GoPay'], ['kind' => MoneyAccountKind::NonTunai]);

        // Opened the way the onboarding wizard does: cash per rekening, then the kuliner starter catalog.
        $openedAt = today();

        foreach (['Kas tunai' => 500_000, 'BCA' => 2_000_000] as $name => $amount) {
            $transactions->record($business, [
                'type' => 'saldo_awal', 'kind' => 'saldo_kas', 'amount' => $amount, 'occurred_at' => $openedAt->toDateTimeString(),
                'money_account_id' => $business->moneyAccounts()->where('name', $name)->value('id'),
            ]);
        }

        $catalog->createMany($business, StarterCatalog::for(BusinessCategory::Kuliner), $openedAt);
        $business->update(['category' => BusinessCategory::Kuliner, 'started_on' => $openedAt, 'onboarded_at' => now()]);
    }
}
