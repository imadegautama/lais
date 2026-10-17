<?php

namespace App\Http\Requests;

use App\Models\MoneyAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The name of a rekening: what the owner calls it, unique in the business.
 */
class MoneyAccountRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $moneyAccount = $this->route('moneyAccount');

        return [
            'name' => [
                'required', 'string', 'max:60',
                // Archived rekening keep their name, so a new one cannot reuse it either.
                Rule::unique('money_accounts', 'name')
                    ->where('business_id', $this->user()->currentBusiness()->id)
                    ->ignore($moneyAccount instanceof MoneyAccount ? $moneyAccount->id : null),
            ],
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
            'name.required' => 'Tulis nama rekeningnya, misalnya BCA atau GoPay.',
            'name.max' => 'Nama paling panjang 60 huruf.',
            'name.unique' => 'Nama ini sudah dipakai rekening lain.',
        ];
    }
}
