<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An account a business adds: its name, and on creation whether it is income
 * or an expense. The type cannot change later (PRD §4.4).
 */
class AccountRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $account = $this->route('account');
        $editing = $account instanceof Account;

        return [
            'name' => [
                'required', 'string', 'max:60',
                // Archived accounts keep their name, so a new one cannot reuse it either.
                Rule::unique('accounts', 'name')
                    ->where('business_id', $this->user()->currentBusiness()->id)
                    ->ignore($editing ? $account->id : null),
                // Nor can it pass for one of the fixed accounts in a report.
                Rule::unique('accounts', 'name')->whereNull('business_id'),
            ],
            'type' => $editing ? ['prohibited'] : ['required', Rule::in([AccountType::Pendapatan->value, AccountType::Beban->value])],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tulis nama akunnya, misalnya Beban Internet.',
            'name.max' => 'Nama paling panjang 60 huruf.',
            'name.unique' => 'Nama ini sudah dipakai akun lain.',
            'type.required' => 'Pilih pendapatan atau beban.',
            'type.in' => 'Akun baru hanya bisa berupa pendapatan atau beban.',
            'type.prohibited' => 'Jenis akun tidak bisa diubah.',
        ];
    }
}
