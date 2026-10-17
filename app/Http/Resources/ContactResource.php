<?php

namespace App\Http\Resources;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A contact with its open balance, which lives in the journal (Ledger::contactBalances()).
 *
 * @mixin Contact
 */
class ContactResource extends JsonResource
{
    public function __construct(Contact $resource, private int $balance = 0)
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
            'type' => $this->type,
            'name' => $this->name,
            'balance' => $this->balance,
        ];
    }
}
