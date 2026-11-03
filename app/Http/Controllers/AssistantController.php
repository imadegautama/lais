<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssistantRequest;
use App\Services\Ai\FormAssistant;
use Illuminate\Http\JsonResponse;

class AssistantController extends Controller
{
    /**
     * "Tanya AI" (UI-PAGES C19): drafts a record or product from what the
     * owner writes, and saves it once the owner confirms that draft.
     */
    public function store(AssistantRequest $request, FormAssistant $assistant): JsonResponse
    {
        $validated = $request->validated();

        return response()->json($assistant->reply(
            $request->user(),
            $validated['message'] ?? '',
            $validated['form'] ?? null,
            array_values($validated['history'] ?? []),
            $validated['current'] ?? [],
            $validated['draft'] ?? null,
            $validated['photo'] ?? null,
        ));
    }
}
