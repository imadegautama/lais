<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shape checks for the five transaction types. Money rules that need the
 * ledger (balances, paid in full) live in TransactionService.
 */
class TransactionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = $this->user()->currentBusiness()->id;
        $type = (string) $this->input('type');
        $kind = (string) $this->input('kind');
        $method = (string) $this->input('method');
        $hasLines = $type === 'penjualan' || ($type === 'pembelian' && $kind === 'barang');
        $contactType = $type === 'penjualan' || $kind === 'terima_piutang' ? 'pelanggan' : 'supplier';
        $needsContact = $method === 'kredit' || $type === 'utang_piutang';

        return [
            'type' => ['required', Rule::in(Transaction::TYPES)],
            'kind' => $type === 'penjualan' ? ['prohibited'] : ['required', Rule::in(Transaction::KINDS[$type] ?? [])],
            'method' => ['required', Rule::in(Transaction::METHODS[$type] ?? [])],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
            'note' => [$type === 'pembelian' && $kind === 'peralatan' ? 'required' : 'nullable', 'string', 'max:200'],
            'lines' => $hasLines ? ['required', 'array', 'min:1', 'max:50'] : ['prohibited'],
            'lines.*.item_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('items', 'id')
                    ->where('business_id', $businessId)
                    ->whereIn('kind', $type === 'penjualan' ? ['produk', 'komposisi'] : ['produk', 'bahan'])
                    ->whereNull('deleted_at'),
            ],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'lines.*.price' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'amount' => $hasLines ? ['prohibited'] : ['required', 'integer', 'min:1', 'max:1000000000000'],
            'paid_amount' => $type === 'penjualan' && $method === 'kredit' ? ['nullable', 'integer', 'min:0'] : ['prohibited'],
            'contact_id' => [
                Rule::requiredIf(fn () => $needsContact && trim((string) $this->input('contact_name')) === ''),
                'nullable', 'integer',
                Rule::exists('contacts', 'id')->where('business_id', $businessId)->where('type', $contactType),
            ],
            // Paying off a balance needs an existing contact; there is nothing to pay to a new one.
            'contact_name' => in_array($type, ['modal', 'utang_piutang'], true) ? ['prohibited'] : ['nullable', 'string', 'max:120'],
            'again' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $type = (string) $this->input('type');
        $kind = (string) $this->input('kind');

        return [
            'type.required' => 'Pilih jenis catatannya.',
            'type.in' => 'Jenis catatan tidak dikenal.',
            'kind.required' => match ($type) {
                'pembelian' => 'Pilih yang dibeli: bahan/barang atau peralatan.',
                'biaya' => 'Pilih jenis biayanya.',
                'utang_piutang' => 'Pilih terima bayaran atau bayar utang.',
                default => 'Pilih setor modal atau ambil untuk pribadi.',
            },
            'method.required' => 'Pilih cara bayarnya.',
            'method.in' => 'Cara bayar ini tidak bisa dipakai untuk jenis catatan ini.',
            'occurred_at.date' => 'Tanggal tidak valid.',
            'occurred_at.before_or_equal' => 'Waktunya tidak boleh di masa depan.',
            'note.required' => 'Tulis nama peralatannya, misalnya kompor dua tungku.',
            'note.max' => 'Catatan paling panjang 200 huruf.',
            'lines.required' => $type === 'penjualan' ? 'Tambahkan barang yang terjual.' : 'Tambahkan barang yang dibeli.',
            'lines.min' => $type === 'penjualan' ? 'Tambahkan barang yang terjual.' : 'Tambahkan barang yang dibeli.',
            'lines.max' => 'Paling banyak 50 barang dalam satu catatan.',
            'lines.*.item_id.required' => 'Pilih barangnya.',
            'lines.*.item_id.distinct' => 'Barang ini sudah ada di daftar. Tambah jumlahnya saja.',
            'lines.*.item_id.exists' => $type === 'penjualan' ? 'Barang ini tidak dijual atau sudah diarsipkan.' : 'Barang ini bukan bahan atau produk biasa.',
            'lines.*.qty.required' => 'Isi jumlahnya.',
            'lines.*.qty.gt' => 'Jumlah harus lebih dari 0.',
            'lines.*.price.required' => 'Isi harganya.',
            'lines.*.price.min' => 'Harga tidak boleh minus.',
            'amount.required' => 'Isi nominalnya.',
            'amount.min' => 'Nominal harus lebih dari Rp0.',
            'paid_amount.min' => 'Nominal yang sudah dibayar tidak boleh minus.',
            'contact_id.required' => $type === 'penjualan' || $kind === 'terima_piutang'
                ? 'Pilih atau tambahkan nama pelanggan yang belum bayar.'
                : 'Pilih atau tambahkan nama suppliernya.',
            'contact_id.exists' => 'Kontak tidak ditemukan.',
            'contact_name.max' => 'Nama paling panjang 120 huruf.',
        ];
    }
}
