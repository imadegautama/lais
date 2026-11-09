<?php

namespace App\Http\Requests;

use App\Services\Ai\FormAssistant;
use Closure;
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
            'message' => ['required_without:photo', 'nullable', 'string', 'max:1000'],
            // A receipt photo, shrunk in the browser (lib/image.ts) and sent as a data URI. Never stored.
            'photo' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! self::isPhoto($value)) {
                    $fail('Foto harus berupa gambar JPG, PNG, atau WebP sampai 5 MB.');
                }
            }],
            'form' => ['nullable', Rule::in(FormAssistant::FORMS)],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'history.*.text' => ['required', 'string', 'max:1000'],
            'current' => ['nullable', 'array'],
            // The report page open behind the panel, and its period as in its address.
            'report' => ['nullable', 'array'],
            'report.page' => ['required_with:report', Rule::in(array_keys(FormAssistant::REPORTS))],
            'report.periode' => ['nullable', 'string', 'max:20'],
            'report.dari' => ['nullable', 'string', 'max:10'],
            'report.sampai' => ['nullable', 'string', 'max:10'],
            'draft' => ['nullable', 'array'],
            'draft.form' => ['required_with:draft', Rule::in(FormAssistant::DRAFTS)],
            'draft.values' => ['required_with:draft', 'array'],
            // The receipt the draft was read from, kept as the record's proof when it is saved.
            'draft.photo' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! self::isPhoto($value)) {
                    $fail('Foto harus berupa gambar JPG, PNG, atau WebP sampai 5 MB.');
                }
            }],
        ];
    }

    /**
     * A data URI of a real JPG, PNG, or WebP image of at most 5 MB (PRD §5).
     */
    public static function isPhoto(mixed $value): bool
    {
        if (! is_string($value) || preg_match('#^data:image/(jpeg|png|webp);base64,(.+)$#s', $value, $parts) !== 1) {
            return false;
        }

        $bytes = base64_decode($parts[2], true);

        if ($bytes === false || strlen($bytes) > 5 * 1024 * 1024) {
            return false;
        }

        $image = @getimagesizefromstring($bytes);

        return $image !== false && in_array($image[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required_without' => 'Tulis dulu apa yang mau dicatat, atau kirim foto notanya.',
            'message.max' => 'Pesannya terlalu panjang. Pecah jadi beberapa pesan.',
        ];
    }
}
