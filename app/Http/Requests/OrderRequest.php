<?php

namespace App\Http\Requests;

use App\Enums\OrderFulfillment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An order as the customer writes it on the shop page (PRD US-08), or the
 * owner for one taken by phone. Only item ids and quantities come in: the
 * prices are the catalog's (Orders::place).
 */
class OrderRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'regex:/^(\+?62|0)8[0-9]{7,12}$/'],
            'fulfillment' => ['required', Rule::enum(OrderFulfillment::class)],
            'address' => ['nullable', 'required_if:fulfillment,'.OrderFulfillment::Antar->value, 'string', 'max:300'],
            'wanted_at' => ['nullable', 'date', 'after_or_equal:'.now()->subMinutes(5)->toDateTimeString()],
            'note' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.item_id' => ['required', 'integer', 'distinct'],
            'lines.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
            // Left empty by people; bots fill every field they find (PRD US-08).
            'website' => ['prohibited'],
        ];
    }

    /**
     * Phone numbers are typed with spaces and dashes; the rule reads digits.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['customer_phone' => preg_replace('/[\s\-().]/', '', (string) $this->input('customer_phone'))]);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'Tulis nama pemesan.',
            'customer_phone.required' => 'Tulis nomor WA supaya penjual bisa menghubungi.',
            'customer_phone.regex' => 'Nomor WA diawali 08 atau 62, misalnya 081234567890.',
            'address.required_if' => 'Tulis alamat pengantaran.',
            'wanted_at.after_or_equal' => 'Waktunya sudah lewat. Pilih waktu nanti.',
            'lines.required' => 'Pilih minimal satu produk.',
            'lines.max' => 'Maksimal 20 jenis produk dalam satu pesanan.',
            'lines.*.qty.max' => 'Jumlah per produk paling banyak 999.',
            'website.prohibited' => 'Pesanan tidak bisa dikirim.',
        ];
    }
}
