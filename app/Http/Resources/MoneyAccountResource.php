<?php

namespace App\Http\Resources;

use App\Models\MoneyAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A rekening with its balance, which lives in the journal (Ledger::moneyBalances()).
 *
 * @mixin MoneyAccount
 */
class MoneyAccountResource extends JsonResource
{
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
