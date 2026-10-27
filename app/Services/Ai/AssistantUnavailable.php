<?php

namespace App\Services\Ai;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The model could not be reached or answered without a tool call. Nothing
 * was saved, so the owner simply fills the form by hand.
 */
class AssistantUnavailable extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'AI sedang tidak bisa dipakai. Isi form secara manual dulu, atau coba lagi sebentar lagi.'], 503);
    }
}
