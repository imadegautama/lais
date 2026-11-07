<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The shop's switches and contact (UI-PAGES C9/C20): open for orders, the
 * owner's WA number for "Hubungi penjual", and the QRIS image customers pay to.
 */
class ShopSettingsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'accepting_orders' => ['sometimes', 'boolean'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'regex:/^(\+?62|0)8[0-9]{7,12}$/'],
            'qris' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_qris' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('whatsapp')) {
            $this->merge(['whatsapp' => preg_replace('/[\s\-().]/', '', (string) $this->input('whatsapp')) ?: null]);
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'whatsapp.regex' => 'Nomor WA diawali 08 atau 62, misalnya 081234567890.',
            'qris.image' => 'QRIS harus berupa gambar JPG, PNG, atau WebP.',
            'qris.mimes' => 'QRIS harus berupa gambar JPG, PNG, atau WebP.',
            'qris.max' => 'Ukuran gambar QRIS maksimal 5 MB.',
        ];
    }
}
