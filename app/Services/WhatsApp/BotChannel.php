<?php

namespace App\Services\WhatsApp;

use App\Enums\TransactionSource;
use App\Models\WaMessage;

/**
 * The chat app the bot runs on, picked by BOT_CHANNEL. Everything above it
 * (Inbox, Formatter, OrderCommands, DailySummary) is the same on either.
 *
 * shortcut: the bot's classes and tables keep their WhatsApp names while
 * Telegram stands in; rename them to Bot if Telegram stays after the contest.
 */
interface BotChannel
{
    /**
     * @param  string  $to  where the owner is reached (User::whatsappAddress)
     * @return string|null the channel's id for the sent message
     */
    public function send(string $to, string $text): ?string;

    /**
     * The photo in a message, as a JPEG data URI for the assistant.
     */
    public function downloadImage(WaMessage $message): string;

    /** Recorded on the transactions the bot saves. */
    public function source(): TransactionSource;

    public function name(): string;

    /** How owners find the bot; null until it is set up. */
    public function contact(): ?string;

    /** Opens the chat with the link code ready to send; null until set up. */
    public function linkUrl(string $code): ?string;
}
