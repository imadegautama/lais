<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Enums\BusinessCategory;
use App\Enums\ItemKind;
use App\Services\Onboarding;
use App\Support\Format;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The onboarding wizard. Saving a draft (PATCH) checks the step just left;
 * finishing (POST) checks them all. List sizes are always capped, because the
 * draft keeps every step.
 */
class OnboardingRequest extends FormRequest
{
    /**
     * In the order the wizard shows them.
     */
    public const STEPS = ['usaha', 'uang', 'peralatan', 'produk', 'utang', 'akun'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $finishing = $this->isMethod('post');
        $rules = [
            'step' => $finishing ? ['nullable'] : ['required', Rule::in(self::STEPS)],
            // The form as typed, stored for resuming; its lists are capped like the payload's.
            'draft' => $finishing ? ['prohibited'] : ['required', 'array'],
            'draft.accounts' => ['nullable', 'array', 'max:20'],
            'draft.equipment' => ['nullable', 'array', 'max:30'],
            'draft.products' => ['nullable', 'array', 'max:100'],
            'draft.debts' => ['nullable', 'array', 'max:50'],
            'draft.receivables' => ['nullable', 'array', 'max:50'],
            'draft.custom_accounts' => ['nullable', 'array', 'max:10'],
            'accounts' => ['nullable', 'array', 'max:20'],
            'equipment' => ['nullable', 'array', 'max:30'],
            'products' => ['nullable', 'array', 'max:100'],
            'debts' => ['nullable', 'array', 'max:50'],
            'receivables' => ['nullable', 'array', 'max:50'],
            'custom_accounts' => ['nullable', 'array', 'max:10'],
        ];

        foreach ($finishing ? self::STEPS : [(string) $this->input('step')] as $step) {
            $rules = match ($step) {
                'usaha' => $this->business() + $rules,
                'uang' => $this->money() + $rules,
                'peralatan' => $this->amounts('equipment') + $rules,
                'produk' => $this->products() + $rules,
                'utang' => $this->amounts('debts') + $this->amounts('receivables') + $rules,
                'akun' => $this->customAccounts() + $rules,
                default => $rules,
            };
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $latest = Format::date(Onboarding::latestStart($this->user()->currentBusiness()));

        return [
            'name.required' => 'Tulis nama usahanya.',
            'name.max' => 'Nama usaha paling panjang 120 huruf.',
            'started_on.required' => 'Pilih tanggal mulai mencatat.',
            'started_on.date' => 'Tanggal tidak valid.',
            'started_on.before_or_equal' => "Paling lambat {$latest}: saldo awal harus ada sebelum catatan pertama, dan tidak boleh di masa depan.",
            'accounts.required' => 'Sebutkan minimal satu tempat uang usaha.',
            'accounts.*.name.required' => 'Tulis nama rekeningnya.',
            'accounts.*.name.distinct' => 'Nama rekening ini dipakai dua kali.',
            'accounts.*.name.unique' => 'Nama ini sudah dipakai rekening lain.',
            'accounts.*.name.max' => 'Nama paling panjang 60 huruf.',
            'accounts.*.balance.min' => 'Saldo tidak boleh minus. Kalau rekeningnya kosong, isi 0.',
            '*.*.name.required' => 'Tulis namanya.',
            '*.*.name.max' => 'Nama paling panjang 120 huruf.',
            '*.*.amount.required' => 'Isi nilainya.',
            '*.*.amount.min' => 'Nilainya harus lebih dari Rp0.',
            'products.*.name.distinct' => 'Nama ini ada dua kali di daftar.',
            'products.*.name.unique' => 'Produk dengan nama ini sudah ada.',
            'products.*.unit.required' => 'Isi satuannya, misalnya pcs atau kg.',
            'products.*.price.required' => 'Isi harga jualnya.',
            'products.*.opening_cost.required' => 'Isi biaya per unit untuk stok awalnya.',
            'products.*.opening_stock.min' => 'Stok awal tidak boleh minus.',
            'products.*.opening_stock.in' => 'Produk dengan resep tidak punya stok sendiri; stoknya ada di bahan.',
            'products.*.recipe.required' => 'Produk dengan resep perlu minimal satu bahan.',
            'custom_accounts.max' => 'Paling banyak 10 akun khusus. Sisanya bisa ditambah nanti di Daftar akun.',
            'custom_accounts.*.name.distinct' => 'Nama akun ini ada dua kali.',
            'custom_accounts.*.name.unique' => 'Nama ini sudah dipakai akun lain.',
            'custom_accounts.*.name.max' => 'Nama akun paling panjang 60 huruf.',
            'custom_accounts.*.type.in' => 'Akun khusus hanya untuk pendapatan atau beban.',
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function business(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', Rule::enum(BusinessCategory::class)],
            'started_on' => ['required', 'date', 'before_or_equal:'.Onboarding::latestStart($this->user()->currentBusiness())->toDateString()],
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function money(): array
    {
        $businessId = $this->user()->currentBusiness()->id;
        $rules = ['accounts' => ['required', 'array', 'min:1', 'max:20']];

        foreach (array_keys((array) $this->input('accounts', [])) as $index) {
            $id = $this->input("accounts.{$index}.id");
            $rules["accounts.{$index}.id"] = ['nullable', 'integer', Rule::exists('money_accounts', 'id')->where('business_id', $businessId)->whereNull('deleted_at')];
            // Archived rekening keep their name, so a new one cannot reuse it either.
            $rules["accounts.{$index}.name"] = ['required', 'string', 'max:60', 'distinct', Rule::unique('money_accounts', 'name')->where('business_id', $businessId)->ignore(is_numeric($id) ? (int) $id : null)];
            $rules["accounts.{$index}.balance"] = ['nullable', 'integer', 'min:0', 'max:1000000000000'];
        }

        return $rules;
    }

    /**
     * "Akun khusus": income and expense accounts of the business's own, named
     * like AccountRequest names them (unique in the business and the fixed chart).
     *
     * @return array<string, array<mixed>>
     */
    private function customAccounts(): array
    {
        return [
            'custom_accounts.*.type' => ['required', Rule::in([AccountType::Pendapatan->value, AccountType::Beban->value])],
            'custom_accounts.*.name' => [
                'required', 'string', 'max:60', 'distinct',
                Rule::unique('accounts', 'name')->where('business_id', $this->user()->currentBusiness()->id),
                Rule::unique('accounts', 'name')->whereNull('business_id'),
            ],
        ];
    }

    /**
     * Rows of a name and an amount: equipment, debts, receivables.
     *
     * @return array<string, array<mixed>>
     */
    private function amounts(string $list): array
    {
        return [
            "{$list}.*.name" => ['required', 'string', 'max:120'],
            "{$list}.*.amount" => ['required', 'integer', 'min:1', 'max:1000000000000'],
        ];
    }

    /**
     * Quick-add products: the essentials of ItemRequest, decided per row by its kind.
     *
     * @return array<string, array<mixed>>
     */
    private function products(): array
    {
        $businessId = $this->user()->currentBusiness()->id;
        $rules = [];

        foreach ((array) $this->input('products', []) as $index => $row) {
            $kind = ItemKind::tryFrom((string) ($row['kind'] ?? ''));
            $stocked = $kind !== ItemKind::Komposisi;
            $prefix = "products.{$index}";

            $rules += [
                "{$prefix}.name" => ['required', 'string', 'max:120', 'distinct', Rule::unique('items', 'name')->where('business_id', $businessId)->whereNull('deleted_at')],
                "{$prefix}.kind" => ['required', Rule::enum(ItemKind::class)],
                "{$prefix}.unit" => ['required', 'string', 'max:20'],
                "{$prefix}.aliases" => ['nullable', 'array', 'max:10'],
                "{$prefix}.aliases.*" => ['string', 'max:60'],
                "{$prefix}.price" => $kind === ItemKind::Bahan ? ['nullable'] : ['required', 'integer', 'min:0', 'max:1000000000000'],
                // A recipe product has no stock of its own; the starter rows still carry zeros.
                "{$prefix}.min_stock" => ['nullable', 'numeric', 'min:0'],
                "{$prefix}.opening_stock" => $stocked ? ['nullable', 'numeric', 'min:0', 'max:99999'] : ['nullable', 'numeric', 'in:0'],
                "{$prefix}.opening_cost" => $stocked && (float) ($row['opening_stock'] ?? 0) > 0
                    ? ['required', 'integer', 'min:0', 'max:1000000000000']
                    : ['nullable', 'integer', 'min:0'],
                "{$prefix}.recipe" => $stocked ? ['prohibited'] : ['required', 'array', 'min:1', 'max:30'],
                "{$prefix}.recipe.*.name" => ['required', 'string', 'max:120'],
                "{$prefix}.recipe.*.qty" => ['required', 'numeric', 'gt:0'],
            ];
        }

        return $rules;
    }
}
