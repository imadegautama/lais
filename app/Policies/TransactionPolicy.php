<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TransactionPolicy
{
    /**
     * Another business's transaction answers 404, so its existence is not revealed.
     */
    public function manage(User $user, Transaction $transaction): Response
    {
        return $user->business?->id === $transaction->business_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
