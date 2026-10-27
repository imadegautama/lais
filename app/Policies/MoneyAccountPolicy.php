<?php

namespace App\Policies;

use App\Models\MoneyAccount;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MoneyAccountPolicy
{
    /**
     * Rekening of another business answer 404, so their existence is not revealed.
     */
    public function manage(User $user, MoneyAccount $moneyAccount): Response
    {
        return $user->business?->id === $moneyAccount->business_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
