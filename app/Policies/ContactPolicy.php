<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ContactPolicy
{
    /**
     * Another business's contact answers 404, so its existence is not revealed.
     */
    public function manage(User $user, Contact $contact): Response
    {
        return $user->business?->id === $contact->business_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
