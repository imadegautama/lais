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
    public function __construct(private Catalog $catalog, private TransactionService $transactions) {}

    public function run(): void
    {
        $user = User::query()->where('email', 'demo@lais.test')->first() ?? User::query()->first();

        if ($user) {
            $this->open($user, 'warung-bu-rina');
        }
    }

    /**
     * Warung Bu Rina for this account, also each "Coba demo" copy (App\Services\DemoAccounts).
     */
    public function open(User $user, string $slug): Business
    {
        $business = Business::query()->firstOrCreate(
            ['user_id' => $user->id],
            // Bayar online shows only once DOKU keys are set (App\Services\Doku::offeredBy).
            ['name' => 'Warung Bu Rina', 'slug' => $slug, 'online_payment' => true],
        );

        if ($business->items()->exists()) {
            return $business;
        }

        // The rekening a warung typically has: its bank and an e-wallet for QRIS.
        $business->moneyAccounts()->where('kind', MoneyAccountKind::NonTunai)->first()?->update(['name' => 'BCA']);
        $gopay = $business->moneyAccounts()->firstOrCreate(['name' => 'GoPay'], ['kind' => MoneyAccountKind::NonTunai]);
        // "jual … qris" in the demo lands here without naming it.
        $business->update(['qris_money_account_id' => $gopay->id]);

        // Opened the way the onboarding wizard does: cash per rekening, then the kuliner starter catalog.
        $openedAt = today()->subDays(DemoHistorySeeder::DAYS);

        foreach (['Kas tunai' => 1_500_000, 'BCA' => 6_000_000] as $name => $amount) {
            $this->transactions->record($business, [
                'type' => 'saldo_awal', 'kind' => 'saldo_kas', 'amount' => $amount, 'occurred_at' => $openedAt->toDateTimeString(),
                'money_account_id' => $business->moneyAccounts()->where('name', $name)->value('id'),
            ]);
        }

        $this->catalog->createMany($business, StarterCatalog::for(BusinessCategory::Kuliner), $openedAt);
        $business->update(['category' => BusinessCategory::Kuliner, 'started_on' => $openedAt, 'onboarded_at' => now()]);

        return $business;
    }
}
