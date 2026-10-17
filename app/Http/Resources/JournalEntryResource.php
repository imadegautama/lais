<?php

namespace App\Http\Resources;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A journal entry with its lines, for "Lihat jurnal". Load lines.account.
 *
 * @mixin JournalEntry
 */
class JournalEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'occurredAt' => $this->occurred_at->toIso8601String(),
            'lines' => $this->lines->map(fn (JournalLine $line) => [
                'code' => $line->account->code,
                'account' => $line->account->name,
                // Load lines.moneyAccount: "Kas Non-Tunai" alone does not say which rekening.
                'moneyAccount' => $line->moneyAccount?->name,
                'debit' => $line->debit,
                'credit' => $line->credit,
            ])->values()->all(),
        ];
    }
}
