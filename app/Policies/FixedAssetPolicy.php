<?php

namespace App\Policies;

use App\Models\FixedAsset;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FixedAssetPolicy
{
    /**
     * Another business's asset answers 404, so its existence is not revealed.
     */
    public function manage(User $user, FixedAsset $fixedAsset): Response
    {
        return $user->business?->id === $fixedAsset->business_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
