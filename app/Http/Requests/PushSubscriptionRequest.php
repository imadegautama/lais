<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What the browser's PushSubscription.toJSON() gives: where to deliver, and
 * the keys to encrypt with. Only https push services are taken.
 */
class PushSubscriptionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:2000', 'url:https'],
            'keys.p256dh' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9_-]+={0,2}$/'],
            'keys.auth' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+={0,2}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'endpoint.*' => 'Browser ini tidak memberi alamat notifikasi yang bisa dipakai.',
            'keys.*' => 'Browser ini tidak memberi kunci notifikasi yang bisa dipakai.',
        ];
    }
}
