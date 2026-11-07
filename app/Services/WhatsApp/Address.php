<?php

namespace App\Services\WhatsApp;

/**
 * Who a message came from. WhatsApp names a sender by number
 * (628…@s.whatsapp.net) or, for some accounts, only by a linked id (…@lid).
 */
final readonly class Address
{
    public function __construct(
        /** 62…, when the sender's number is known */
        public ?string $phone,
        /** the full …@lid id, when given */
        public ?string $lid,
        /** where a reply goes: the JID or chat the message came from */
        public string $replyTo,
        /** the Telegram chat id, when the bot runs there */
        public ?string $telegram = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  GOWA's message payload, or what Telegram::receive kept
     */
    public static function from(array $payload): self
    {
        if (isset($payload['telegram_chat_id'])) {
            return new self(null, null, (string) $payload['telegram_chat_id'], (string) $payload['telegram_chat_id']);
        }

        $jids = array_values(array_filter([$payload['from'] ?? null, $payload['chat_id'] ?? null, $payload['from_lid'] ?? null], 'is_string'));
        $phone = null;
        $lid = null;

        foreach ($jids as $jid) {
            if ($phone === null && str_ends_with($jid, '@s.whatsapp.net')) {
                $phone = strtok($jid, '@:') ?: null;
            }

            if ($lid === null && str_ends_with($jid, '@lid')) {
                $lid = $jid;
            }
        }

        return new self($phone, $lid, (string) ($payload['from'] ?? $jids[0] ?? ''));
    }
}
