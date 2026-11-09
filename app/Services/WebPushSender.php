<?php

namespace App\Services;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * The one place that talks to push services, so tests can stand in for it:
 * the library uses its own HTTP client, out of Http::fake()'s reach.
 */
class WebPushSender
{
    /**
     * Sends one notification; false when the push service says the
     * subscription is gone (the browser unsubscribed or was reset).
     *
     * @param  array{title: string, body: string, url: string}  $message
     */
    public function send(PushSubscription $subscription, array $message): bool
    {
        $report = $this->client()->sendOneNotification(
            new Subscription($subscription->endpoint, $subscription->public_key, $subscription->auth_token, 'aes128gcm'),
            (string) json_encode($message, JSON_UNESCAPED_UNICODE),
            ['TTL' => 3600],
        );

        return ! $report->isSubscriptionExpired();
    }

    private function client(): WebPush
    {
        return new WebPush(['VAPID' => [
            'subject' => (string) config('services.webpush.subject'),
            'publicKey' => (string) config('services.webpush.public_key'),
            'privateKey' => (string) config('services.webpush.private_key'),
        ]]);
    }
}
