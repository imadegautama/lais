<?php

namespace App\Http\Requests\Settings;

use App\Enums\BusinessCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pengaturan > Usaha: the name and kind the onboarding wizard asked for,
 * corrected later. The shop link (slug) stays, so shared links keep working.
 */
class BusinessUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', Rule::enum(BusinessCategory::class)],
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
            'name.required' => 'Isi nama usaha.',
            'name.max' => 'Nama usaha maksimal 120 huruf.',
            'category' => 'Pilih jenis usaha dari daftar.',
        ];
    }
}
