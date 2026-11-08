<?php

namespace App\Support;

use App\Enums\OrderFulfillment;
use App\Enums\OrderStatus;
use App\Enums\TransactionKind;
use App\Enums\TransactionStatus;
use App\Models\Order;
use App\Services\WhatsApp\Buttons;

/**
 * Words about an order, in one place for the web and the bot: the news the
 * owner forwards to the customer from their own WhatsApp ("Kirim kabar",
 * PRD US-09), and the bot's note to the owner about a new order.
 */
class OrderMessages
{
    /**
     * Where the order stands and what is left to pay, with the nota link.
     * A refused order says what uang muka comes back instead.
     *
     * @param  int  $paid  Orders::paid(), read by the caller
     */
    public static function status(Order $order, int $paid): string
    {
        $shop = $order->business->name;
        $rest = $order->total - $paid;
        $owed = $rest > 0 ? ' Sisa yang perlu dibayar '.Format::rupiah($rest).'.' : '';
        $nota = "\n\nStatus dan nota: ".route('shop.order', $order->token);
        $greeting = "Halo {$order->customer_name},";

        return match ($order->status) {
            OrderStatus::Menunggu => "{$greeting} pesanan {$order->code} di {$shop} sudah kami terima dan sedang dicek.{$nota}",
            OrderStatus::Diproses => "{$greeting} pesanan {$order->code} di {$shop} sedang kami siapkan. Totalnya ".Format::rupiah($order->total).".{$owed}{$nota}",
            OrderStatus::Siap => $order->fulfillment === OrderFulfillment::Antar
                ? "{$greeting} pesanan {$order->code} sedang diantar ke alamat Anda.{$owed}{$nota}"
                : "{$greeting} pesanan {$order->code} sudah siap diambil di {$shop}.{$owed}{$nota}",
            OrderStatus::Selesai => "Terima kasih, {$order->customer_name}! Pesanan {$order->code} sudah selesai.".($rest > 0 ? $owed : ' Pembayarannya sudah lunas.').$nota,
            OrderStatus::Ditolak => "Mohon maaf, {$order->customer_name}, pesanan {$order->code} di {$shop} tidak bisa kami proses"
                .($order->reject_reason ? " ({$order->reject_reason})" : '').'.'
                .(($returned = self::returned($order)) > 0 ? ' Uang muka '.Format::rupiah($returned).' kami kembalikan.' : '').$nota,
        };
    }

    /**
     * Uang muka handed back when an accepted order was called off: Orders::cancel
     * voids it, so paid() no longer counts it. Order records are locked
     * elsewhere, so a void one here can only come from that cancel.
     */
    private static function returned(Order $order): int
    {
        return (int) $order->transactions()
            ->where('transactions.kind', TransactionKind::UangMuka)
            ->where('transactions.status', TransactionStatus::Void)
            ->sum('transactions.total');
    }

    /**
     * The bot's note to the owner when a customer paid online (App\Services\Doku).
     *
     * @param  int  $booked  how much went into the books; less than $amount when the order could not take it
     */
    public static function paidOnline(Order $order, int $amount, int $booked): string
    {
        $head = "*{$order->code} dibayar online* ".Format::rupiah($amount)." oleh {$order->customer_name}.";

        if ($booked === $amount) {
            return $head.'
Dicatat sebagai uang muka di Saldo DOKU, dan pesanannya sudah diterima.';
        }

        $back = Format::rupiah($amount - $booked);

        return $head.($order->status === OrderStatus::Ditolak
            ? "
Pesanan ini sudah ditolak, jadi tidak dicatat. Kembalikan {$back} ke pelanggan lewat dashboard DOKU."
            : '
Yang dicatat '.Format::rupiah($booked).". Lebihnya {$back} kembalikan ke pelanggan lewat dashboard DOKU.");
    }

    /**
     * The bot's note to the owner when a customer orders from the shop.
     */
    public static function arrived(Order $order): string
    {
        $lines = $order->lines->map(fn ($line) => "• {$line->qty} {$line->name}")->implode("\n");
        $when = $order->wanted_at === null ? '' : "\n".$order->fulfillment->label().', '.Format::date($order->wanted_at).' '.$order->wanted_at->format('H:i');

        return "*Pesanan baru {$order->code}* dari {$order->customer_name}\n{$lines}\nTotal: *".Format::rupiah($order->total).'*'
            .$when.($order->note ? "\nCatatan: {$order->note}" : '');
    }

    /**
     * Accept or refuse, under the new-order note (Buttons; typed on WhatsApp).
     *
     * @return list<array{label: string, send?: string, url?: string, command?: bool}>
     */
    public static function decide(Order $order): array
    {
        return [Buttons::command('Terima pesanan', "terima {$order->code}"), Buttons::command('Tolak', "tolak {$order->code}")];
    }

    /**
     * A wa.me link that opens the owner's chat with the customer, the news typed in.
     */
    public static function forwardLink(Order $order, int $paid): string
    {
        return 'https://wa.me/'.$order->customer_phone.'?text='.rawurlencode(self::status($order, $paid));
    }
}
