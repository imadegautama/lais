<?php

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use App\Enums\TransactionType;
use App\Models\FixedAsset;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A recorded transaction as the record form's starting values ("Ubah"), in
 * the shape of RecordFormData in features/transactions/record.ts. Load lines.
 *
 * @mixin Transaction
 */
class TransactionFormResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hasLines = $this->lines->isNotEmpty();

        return [
            'type' => $this->type,
            'kind' => $this->kind ?? '',
            'useful_life_years' => Format::decimalInput(($this->fixedAsset->useful_life_months ?? FixedAsset::DEFAULT_LIFE_MONTHS) / 12),
            'account_id' => $this->account_id === null ? '' : (string) $this->account_id,
            'method' => $this->method,
            'occurred_at' => $this->occurred_at->format('Y-m-d\TH:i'),
            'lines' => $hasLines
                ? $this->lines->map(fn (TransactionLine $line) => [
                    'item_id' => (string) $line->item_id,
                    // As the owner types it: "0,5", not "0.500".
                    'qty' => Format::decimalInput($line->qty),
                    'price' => $line->price,
                ])->values()->all()
                : [['item_id' => '', 'qty' => '1', 'price' => null]],
            'amount' => $hasLines ? null : $this->total,
            'paid_amount' => $this->type === TransactionType::Penjualan && $this->method === PaymentMethod::Kredit ? $this->paid_amount : null,
            'contact' => $this->contact_id === null ? '' : (string) $this->contact_id,
            // Kas tunai is implied by Tunai, so only a chosen rekening is sent back.
            'money_account_id' => $this->method === PaymentMethod::NonTunai || in_array($this->type, [TransactionType::PindahDana, TransactionType::SaldoAwal], true)
                ? (string) $this->money_account_id
                : '',
            'to_money_account_id' => $this->to_money_account_id === null ? '' : (string) $this->to_money_account_id,
            'note' => $this->note ?? '',
        ];
    }
}
