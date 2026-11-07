<?php

namespace App\Jobs;

use App\Models\WaMessage;
use App\Services\WhatsApp\Inbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * An incoming message, handled off the webhook: reading it can take the
 * model up to a minute, and GOWA should hear back at once.
 */
class ProcessWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    // A retry would answer twice; Inbox replies with an apology instead.
    public int $tries = 1;

    public int $timeout = 150;

    public function __construct(public WaMessage $message) {}

    public function handle(Inbox $inbox): void
    {
        if ($this->message->processed_at !== null) {
            return;
        }

        $inbox->handle($this->message);
        $this->message->forceFill(['processed_at' => now()])->save();
    }
}
