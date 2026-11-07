<?php

namespace App\Http\Requests;

use App\Enums\ContactType;
use App\Models\Contact;
use App\Services\Orders;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A customer or supplier the owner adds or corrects by hand. The side is fixed
 * once made, because the contact's bills belong to that side.
 */
class ContactRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'phone' => is_string($phone) ? ((string) preg_replace('/[\s\-().]/', '', $phone) ?: null) : $phone,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $contact = $this->route('contact');
        $creating = ! $contact instanceof Contact;
        $type = $creating ? $this->input('type') : $contact->type->value;

        return [
            'type' => $creating ? ['required', Rule::enum(ContactType::class)] : ['prohibited'],
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('contacts')->where('business_id', $this->user()->currentBusiness()->id)->where('type', $type)->ignore($contact),
            ],
            'phone' => ['nullable', 'string', 'regex:/^(\+?62|0)8[0-9]{7,12}$/'],
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
            'name.required' => 'Nama wajib diisi.',
            'name.unique' => 'Nama ini sudah ada di daftar kontak.',
            'phone.regex' => 'Nomor WA diawali 08 atau 62, misalnya 081234567890.',
        ];
    }

    /**
     * The fields to save, with the number in the form wa.me expects.
     *
     * @return array<string, mixed>
     */
    public function person(): array
    {
        $data = $this->validated();

        return [...$data, 'phone' => isset($data['phone']) ? Orders::phone($data['phone']) : null];
    }
}
