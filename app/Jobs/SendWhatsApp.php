<?php

namespace App\Jobs;

use App\Models\WaMessage;
use App\Services\WhatsApp\BotChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Every message the bot sends goes through here, so its channel being down
 * for a moment delays a reply instead of losing it.
 */
class SendWhatsApp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public string $to, public string $text, public ?int $userId = null) {}

    public function handle(BotChannel $channel): void
    {
        $id = $channel->send($this->to, $this->text);

        // The channel's id stays out of the unique index: logging must never fail after the message went out, or a retry sends it twice.
        WaMessage::query()->create([
            'message_id' => null,
            'payload' => $id === null ? null : ['gowa_id' => $id],
            'direction' => 'out',
            'phone' => $this->to,
            'user_id' => $this->userId,
            'body' => $this->text,
            'processed_at' => now(),
        ]);
    }
}
