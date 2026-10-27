<?php

namespace App\Services;

use App\Enums\MoneyAccountKind;
use App\Enums\TransactionKind;
use App\Enums\TransactionType;
use App\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Opens the books (PRD M0): what the business already had and owed on the day
 * it starts recording, as saldo awal records, plus its starter products.
 * Modal awal is what is left: assets − debts, so the first balance sheet balances.
 */
class Onboarding
{
    public function __construct(private TransactionService $transactions, private Catalog $catalog) {}

    /**
     * The latest day the books can open: today, or the day of the first record
     * already there, so every saldo awal comes before what was recorded.
     */
    public static function latestStart(Business $business): CarbonImmutable
    {
        $today = CarbonImmutable::today();

        return $business->firstRecordAt()?->startOfDay()->min($today) ?? $today;
    }

    /**
     * @param  array<string, mixed>  $data  validated OnboardingRequest data
     */
    public function complete(Business $business, array $data): void
    {
        DB::transaction(function () use ($business, $data) {
            $openedAt = CarbonImmutable::parse((string) $data['started_on'])->startOfDay();
            $opening = fn (TransactionKind $kind, int $amount, array $extra) => $this->transactions->record($business, [
                'type' => TransactionType::SaldoAwal->value,
                'kind' => $kind->value,
                'amount' => $amount,
                'occurred_at' => $openedAt->toDateTimeString(),
            ] + $extra);

            $business->update(['name' => $data['name'], 'category' => $data['category'] ?? null, 'started_on' => $openedAt]);

            foreach ($data['accounts'] as $row) {
                $moneyAccount = isset($row['id'])
                    ? $business->moneyAccounts()->whereKey((int) $row['id'])->firstOrFail()
                    : $business->moneyAccounts()->create(['kind' => MoneyAccountKind::NonTunai, 'name' => $row['name']]);

                if ($moneyAccount->name !== $row['name']) {
                    $moneyAccount->update(['name' => $row['name']]);
                }

                if ((int) ($row['balance'] ?? 0) > 0) {
                    $opening(TransactionKind::SaldoKas, (int) $row['balance'], ['money_account_id' => $moneyAccount->id]);
                }
            }

            foreach ($data['equipment'] ?? [] as $row) {
                $opening(TransactionKind::SaldoPeralatan, (int) $row['amount'], ['note' => $row['name']]);
            }

            $this->catalog->createMany($business, array_values($data['products'] ?? []), $openedAt);

            foreach ($data['debts'] ?? [] as $row) {
                $opening(TransactionKind::SaldoUtang, (int) $row['amount'], ['contact_name' => $row['name']]);
            }

            foreach ($data['receivables'] ?? [] as $row) {
                $opening(TransactionKind::SaldoPiutang, (int) $row['amount'], ['contact_name' => $row['name']]);
            }

            $business->update(['onboarded_at' => now(), 'onboarding_draft' => null]);
        });
    }
}
