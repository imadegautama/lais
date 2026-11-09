<?php

namespace App\Services;

use App\Jobs\SendPush;
use App\Models\Order;
use App\Support\Format;

/**
 * Notifications on the owner's phone or browser (web push), next to the bot:
 * for now only a new order from the shop. Off while the VAPID keys are unset.
 */
class Push
{
    public static function ready(): bool
    {
        return (string) config('services.webpush.public_key') !== '' && (string) config('services.webpush.private_key') !== '';
    }

    public function orderArrived(Order $order): void
    {
        $owner = $order->business->user;

        if (! self::ready() || ! $owner->pushSubscriptions()->exists()) {
            return;
        }

        $order->loadMissing('lines');
        $items = (int) $order->lines->sum('qty');

        SendPush::dispatch($owner->id, [
            'title' => "Pesanan baru {$order->code}",
            'body' => "{$order->customer_name} · {$items} item · ".Format::rupiah($order->total),
            'url' => route('orders.show', $order->code, absolute: false),
        ]);
    }
}
