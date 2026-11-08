<?php

namespace App\Jobs;

use App\Models\WaMessage;
use App\Services\WhatsApp\BotChannel;
use App\Services\WhatsApp\Buttons;
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

    /**
     * @param  list<array{label: string, send?: string, url?: string, command?: bool}>  $buttons  see Buttons
     */
    public function __construct(public string $to, public string $text, public ?int $userId = null, public array $buttons = []) {}

    public function handle(BotChannel $channel): void
    {
        $id = $channel->send($this->to, $this->text, $this->buttons);

        // The channel's id stays out of the unique index: logging must never fail after the message went out, or a retry sends it twice.
        WaMessage::query()->create([
            'message_id' => null,
            'payload' => $id === null ? null : ['gowa_id' => $id],
            'direction' => 'out',
            'phone' => $this->to,
            'user_id' => $this->userId,
            // Written out, so the conversation the model reads back says what was offered.
            'body' => Buttons::asText($this->text, $this->buttons),
            'processed_at' => now(),
        ]);
    }
}
