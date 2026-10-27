<?php

namespace App\Http\Requests;

use App\Services\Ai\FormAssistant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One turn with "Tanya AI": the new message, the last few turns, the open
 * form as the page holds it, and the draft the owner was last shown. Both
 * are only starting points: whatever is saved is judged again by the
 * form's own request.
 */
class AssistantRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'],
            'form' => ['nullable', Rule::in(FormAssistant::FORMS)],
            'history' => ['nullable', 'array', 'max:12'],
            'history.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'history.*.text' => ['required', 'string', 'max:1000'],
            'current' => ['nullable', 'array'],
            'draft' => ['nullable', 'array'],
            'draft.form' => ['required_with:draft', Rule::in(FormAssistant::FORMS)],
            'draft.values' => ['required_with:draft', 'array'],
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
            'message.required' => 'Tulis dulu apa yang mau dicatat.',
            'message.max' => 'Pesannya terlalu panjang. Pecah jadi beberapa pesan.',
        ];
    }
}
