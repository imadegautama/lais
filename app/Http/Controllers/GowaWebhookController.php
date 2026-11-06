<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppMessage;
use App\Models\WaMessage;
use App\Services\WhatsApp\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * GOWA's webhook (PRD §4.1). Checks the signature, keeps the message once,
 * and answers at once; the reply is worked out in a queued job.
 */
class GowaWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = (string) config('services.gowa.webhook_secret');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        abort_unless($secret !== '' && hash_equals($expected, (string) $request->header('X-Hub-Signature-256')), 401);

        $payload = $request->json('payload');
        $payload = is_array($payload) ? $payload : [];
        $address = Address::from($payload);
        $hasContent = trim((string) ($payload['body'] ?? '')) !== '' || ! empty($payload['image']);

        // Only a person's own message to the bot: no echoes of what the bot sent, no groups, no reactions or receipts.
        if ($request->json('event') !== 'message' || ! empty($payload['is_from_me']) || str_ends_with((string) ($payload['chat_id'] ?? $address->replyTo), '@g.us')
            || ! is_string($payload['id'] ?? null) || $address->replyTo === '' || ! $hasContent) {
            return response()->noContent();
        }

        $message = WaMessage::query()->firstOrCreate(['message_id' => $payload['id']], [
            'direction' => 'in',
            'phone' => $address->replyTo,
            'body' => mb_substr(trim((string) ($payload['body'] ?? '')), 0, 2000),
            'payload' => $payload,
        ]);

        if ($message->wasRecentlyCreated) {
            ProcessWhatsAppMessage::dispatch($message);
        }

        return response()->noContent();
    }
}
