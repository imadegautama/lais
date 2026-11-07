<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AccountPolicy
{
    /**
     * Only a business's own account can change. Another business's answers
     * 404, so its existence is not revealed; the fixed chart answers 403.
     */
    public function manage(User $user, Account $account): Response
    {
        if ($account->isSystem()) {
            return Response::deny('Akun sistem tidak bisa diubah.');
        }

        return $user->business?->id === $account->business_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
