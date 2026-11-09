<?php

namespace App\Jobs;

use App\Models\PushSubscription;
use App\Services\WebPushSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One notification to every phone and browser the owner allowed, off the
 * customer's request. A subscription the push service has dropped is removed.
 */
class SendPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /**
     * @param  array{title: string, body: string, url: string}  $message
     */
    public function __construct(public int $userId, public array $message) {}

    public function handle(WebPushSender $sender): void
    {
        PushSubscription::query()->where('user_id', $this->userId)->get()
            ->each(function (PushSubscription $subscription) use ($sender) {
                if (! $sender->send($subscription, $this->message)) {
                    $subscription->delete();
                }
            });
    }
}
