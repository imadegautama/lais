<?php

namespace App\Http\Resources;

use App\Models\Business;
use App\Models\MoneyAccount;
use App\Services\Ledger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A rekening with its balance, which lives in the journal (Ledger::moneyBalances()).
 *
 * @mixin MoneyAccount
 */
class MoneyAccountResource extends JsonResource
{
    /**
     * Every rekening with its balance, for "Catat pembayaran" dialogs.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function options(Business $business, Ledger $ledger): array
    {
        $balances = $ledger->moneyBalances($business);

        return $business->moneyAccounts()->orderBy('money_accounts.id')->get()
            ->map(fn (MoneyAccount $moneyAccount) => (new self($moneyAccount, $balances[$moneyAccount->id] ?? 0))->resolve())
            ->values()->all();
    }

    public function __construct(MoneyAccount $resource, private int $balance = 0)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'name' => $this->name,
            'balance' => $this->balance,
        ];
    }
}
