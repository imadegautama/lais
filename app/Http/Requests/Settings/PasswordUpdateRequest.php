<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // An account made with Google has no old password to give (RequirePasswordIfSet).
        return [
            ...($this->user()->password === null ? [] : ['current_password' => $this->currentPasswordRules()]),
            'password' => $this->passwordRules(),
        ];
    }
}
