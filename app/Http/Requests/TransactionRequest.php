<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Enums\ContactType;
use App\Enums\ItemKind;
use App\Enums\MoneyAccountKind;
use App\Enums\PaymentMethod;
use App\Enums\TransactionKind;
use App\Enums\TransactionType;
use App\Support\Proof;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shape checks for the transaction types. Money rules that need the
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
        $type = TransactionType::tryFrom((string) $this->input('type'));
        $kind = TransactionKind::tryFrom((string) $this->input('kind'));
        $method = PaymentMethod::tryFrom((string) $this->input('method'));
        $hasLines = $type === TransactionType::Penjualan || ($type === TransactionType::Pembelian && $kind === TransactionKind::Barang);
        $needsContact = $method === PaymentMethod::Kredit || $type === TransactionType::UtangPiutang;
        $isTransfer = $type === TransactionType::PindahDana;
        $isOpening = $type === TransactionType::SaldoAwal;
        $isOtherIncome = $type === TransactionType::PemasukanLain;
        // Only an account this business added and still uses; the fixed chart is chosen by the type itself.
        $ownAccount = fn (AccountType $accountType) => Rule::exists('accounts', 'id')
            ->where('business_id', $businessId)
            ->where('type', $accountType->value)
            ->whereNull('archived_at');
        $needsContact = $needsContact || in_array($kind, [TransactionKind::SaldoUtang, TransactionKind::SaldoPiutang], true);
        $activeMoneyAccount = fn (?MoneyAccountKind $kind = null) => Rule::exists('money_accounts', 'id')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->when($kind, fn ($rule) => $rule->where('kind', $kind?->value));

        return [
            'type' => ['required', Rule::enum(TransactionType::class)],
            // Without a known type nothing else can pass; the type error is the one shown.
            'kind' => $type === TransactionType::Penjualan || $isTransfer || $isOtherIncome
                ? ['prohibited']
                : ['required', Rule::in(array_column($type?->kinds() ?? [], 'value'))],
            // A transfer's method follows its source rekening (TransactionService).
            // A transfer's method follows its source rekening, an opening balance's its kind (TransactionService).
            'method' => $isTransfer || $isOpening ? ['prohibited'] : ['required', Rule::in(array_column($type?->methods() ?? [], 'value'))],
            // Non-tunai with one rekening needs none; the service asks when there are more.
            'money_account_id' => match (true) {
                $isTransfer, $kind === TransactionKind::SaldoKas => ['required', 'integer', $activeMoneyAccount()],
                $method === PaymentMethod::NonTunai => ['nullable', 'integer', $activeMoneyAccount(MoneyAccountKind::NonTunai)],
                default => ['prohibited'],
            },
            'to_money_account_id' => $isTransfer ? ['required', 'integer', 'different:money_account_id', $activeMoneyAccount()] : ['prohibited'],
            'account_id' => match ($type) {
                TransactionType::Biaya => ['nullable', 'integer', $ownAccount(AccountType::Beban)],
                TransactionType::PemasukanLain => ['required', 'integer', $ownAccount(AccountType::Pendapatan)],
                default => ['prohibited'],
            },
            'useful_life_months' => in_array($kind, [TransactionKind::Peralatan, TransactionKind::SaldoPeralatan], true)
                ? ['nullable', 'integer', 'min:1', 'max:600']
                : ['prohibited'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
            'note' => [in_array($kind, [TransactionKind::Peralatan, TransactionKind::SaldoPeralatan], true) ? 'required' : 'nullable', 'string', 'max:200'],
            'proof' => Proof::RULES,
            // On "Ubah": drop the proof the old version had instead of carrying it over.
            'remove_proof' => ['boolean'],
            'lines' => $hasLines ? ['required', 'array', 'min:1', 'max:50'] : ['prohibited'],
            'lines.*.item_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('items', 'id')
                    ->where('business_id', $businessId)
                    ->whereIn('kind', $type === TransactionType::Penjualan ? ItemKind::sold() : ItemKind::stocked())
                    ->whereNull('deleted_at'),
            ],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'lines.*.price' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'amount' => $hasLines ? ['prohibited'] : ['required', 'integer', 'min:1', 'max:1000000000000'],
            'paid_amount' => $type === TransactionType::Penjualan && $method === PaymentMethod::Kredit ? ['nullable', 'integer', 'min:0'] : ['prohibited'],
            'contact_id' => $isTransfer || $isOtherIncome ? ['prohibited'] : [
                Rule::requiredIf(fn () => $needsContact && trim((string) $this->input('contact_name')) === ''),
                'nullable', 'integer',
                Rule::exists('contacts', 'id')->where('business_id', $businessId)
                    ->where('type', $type ? ContactType::for($type, $kind)->value : null),
            ],
            // Paying off a balance needs an existing contact; there is nothing to pay to a new one.
            'contact_name' => in_array($type, [TransactionType::Modal, TransactionType::UtangPiutang, TransactionType::PindahDana], true) ? ['prohibited'] : ['nullable', 'string', 'max:120'],
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
        $type = TransactionType::tryFrom((string) $this->input('type'));
        $kind = TransactionKind::tryFrom((string) $this->input('kind'));
        $isSale = $type === TransactionType::Penjualan;

        return [
            ...Proof::MESSAGES,
            'type.required' => 'Pilih jenis catatannya.',
            'type.enum' => 'Jenis catatan tidak dikenal.',
            'kind.required' => match ($type) {
                TransactionType::Pembelian => 'Pilih yang dibeli: bahan/barang atau peralatan.',
                TransactionType::Biaya => 'Pilih jenis biayanya.',
                TransactionType::UtangPiutang => 'Pilih terima bayaran atau bayar utang.',
                TransactionType::SaldoAwal => 'Pilih saldo awal yang dicatat.',
                default => 'Pilih setor modal atau ambil untuk pribadi.',
            },
            'kind.in' => 'Jenis ini tidak cocok dengan jenis catatannya.',
            'useful_life_months.min' => 'Umur pakai paling sedikit 1 bulan.',
            'useful_life_months.max' => 'Umur pakai paling lama 50 tahun.',
            'account_id.required' => 'Pilih akun pendapatannya.',
            'account_id.exists' => 'Akun ini tidak bisa dipakai.',
            'method.required' => 'Pilih cara bayarnya.',
            'method.in' => 'Cara bayar ini tidak bisa dipakai untuk jenis catatan ini.',
            'method.prohibited' => 'Cara bayarnya mengikuti rekening atau jenis catatan ini, jadi tidak perlu dipilih.',
            'occurred_at.date' => 'Tanggal tidak valid.',
            'occurred_at.before_or_equal' => 'Waktunya tidak boleh di masa depan.',
            'note.required' => 'Tulis nama peralatannya, misalnya kompor dua tungku.',
            'note.max' => 'Catatan paling panjang 200 huruf.',
            'lines.required' => $isSale ? 'Tambahkan barang yang terjual.' : 'Tambahkan barang yang dibeli.',
            'lines.min' => $isSale ? 'Tambahkan barang yang terjual.' : 'Tambahkan barang yang dibeli.',
            'lines.max' => 'Paling banyak 50 barang dalam satu catatan.',
            'lines.*.item_id.required' => 'Pilih barangnya.',
            'lines.*.item_id.distinct' => 'Barang ini sudah ada di daftar. Tambah jumlahnya saja.',
            'lines.*.item_id.exists' => $isSale ? 'Barang ini tidak dijual atau sudah diarsipkan.' : 'Barang ini bukan bahan atau produk biasa.',
            'lines.*.qty.required' => 'Isi jumlahnya.',
            'lines.*.qty.gt' => 'Jumlah harus lebih dari 0.',
            'lines.*.price.required' => 'Isi harganya.',
            'lines.*.price.min' => 'Harga tidak boleh minus.',
            'amount.required' => 'Isi nominalnya.',
            'amount.min' => 'Nominal harus lebih dari Rp0.',
            'paid_amount.min' => 'Nominal yang sudah dibayar tidak boleh minus.',
            'contact_id.required' => $type && ContactType::for($type, $kind) === ContactType::Pelanggan
                ? 'Pilih atau tambahkan nama pelanggan yang belum bayar.'
                : 'Pilih atau tambahkan nama suppliernya.',
            'contact_id.exists' => 'Kontak tidak ditemukan.',
            'contact_name.max' => 'Nama paling panjang 120 huruf.',
            'money_account_id.required' => $type === TransactionType::PindahDana ? 'Pilih rekening asalnya.' : 'Pilih rekeningnya.',
            'money_account_id.exists' => 'Rekening tidak ditemukan atau sudah diarsipkan.',
            'to_money_account_id.required' => 'Pilih rekening tujuannya.',
            'to_money_account_id.different' => 'Rekening asal dan tujuan harus berbeda.',
            'to_money_account_id.exists' => 'Rekening tidak ditemukan atau sudah diarsipkan.',
        ];
    }
}
