<?php

namespace App\Http\Requests;

use App\Enums\MoneyAccountKind;
use App\Enums\PaymentMethod;
use App\Support\Proof;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Money paid on an order or a debt: how much, and through which rekening.
 */
class PaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000000'],
            'method' => ['required', Rule::in([PaymentMethod::Tunai->value, PaymentMethod::NonTunai->value])],
            // Non-tunai with one rekening needs none; TransactionService asks when there are more.
            'money_account_id' => [
                'nullable', 'integer', 'prohibited_unless:method,'.PaymentMethod::NonTunai->value,
                Rule::exists('money_accounts', 'id')
                    ->where('business_id', $this->user()->currentBusiness()->id)
                    ->where('kind', MoneyAccountKind::NonTunai->value)
                    ->whereNull('deleted_at'),
            ],
            // The customer's transfer screenshot, or the receipt from the supplier.
            'proof' => Proof::RULES,
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
            'amount.required' => 'Isi nominal yang dibayar.',
            'amount.min' => 'Nominal harus lebih dari Rp0.',
            'money_account_id.exists' => 'Rekening tidak ditemukan atau sudah diarsipkan.',
            ...Proof::MESSAGES,
        ];
    }
}
