<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ItemPolicy
{
    /**
     * Items of another business answer 404, so their existence is not revealed.
     */
    public function manage(User $user, Item $item): Response
    {
        return $user->business?->id === $item->business_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
