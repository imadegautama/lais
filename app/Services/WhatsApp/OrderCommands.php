<?php

namespace App\Services\WhatsApp;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders;
use App\Support\Format;
use App\Support\OrderMessages;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Order replies in the chat (PRD US-09), read without the model:
 * "terima A012", "tolak A012 bahan habis", "A012 siap", "A012 selesai",
 * "batal A012 …", "A012 dp 20rb qris", "A012 lunas". Each goes through
 * Orders, like the buttons on the web. On Telegram the next step comes as
 * a button.
 *
 * @phpstan-import-type Button from Buttons
 */
class OrderCommands
{
    private const VERBS = 'terima|tolak|siap|selesai|batal|dp|lunas';

    public function __construct(private Orders $orders, private BotChannel $channel) {}

    /**
     * The reply with the next step as a button, or null when the text is not
     * an order command.
     *
     * @return array{text: string, buttons: list<Button>}|null
     */
    public function handle(User $owner, string $text): ?array
    {
        $verbs = self::VERBS;

        if (preg_match("/^(?:({$verbs})\\s+([a-z]\\d{3,})|([a-z]\\d{3,})\\s+({$verbs}))(?:\\s+(.*))?$/iu", trim($text), $match) !== 1) {
            return null;
        }

        $verb = mb_strtolower(($match[1] ?? '') ?: ($match[4] ?? ''));
        $code = mb_strtoupper(($match[2] ?? '') ?: ($match[3] ?? ''));
        $rest = trim($match[5] ?? '');
        $order = $owner->currentBusiness()->orders()->where('orders.code', $code)->with(['lines', 'business'])->first();

        if ($order === null) {
            return ['text' => "Pesanan {$code} tidak ditemukan. Cek kodenya di halaman Pesanan.", 'buttons' => []];
        }

        try {
            $done = $this->run($order, $verb, $rest);
        } catch (ValidationException $exception) {
            return ['text' => (string) Arr::first(Arr::flatten($exception->errors())), 'buttons' => []];
        }

        $order->refresh();
        $paid = $this->orders->paid($order);
        $next = match ($order->status) {
            OrderStatus::Diproses => [Buttons::command('Tandai siap', "siap {$code}")],
            OrderStatus::Siap => [Buttons::command('Selesai', "selesai {$code}")],
            default => [],
        };

        return ['text' => $done, 'buttons' => [...$next, ['label' => 'Kabari pelanggan lewat WhatsApp', 'url' => OrderMessages::forwardLink($order, $paid)]]];
    }

    private function run(Order $order, string $verb, string $rest): string
    {
        $code = $order->code;

        switch ($verb) {
            case 'terima':
                $this->orders->accept($order);

                return "Pesanan {$code} diterima dan sedang diproses.";
            case 'tolak':
                $this->orders->reject($order, $rest ?: null);

                return "Pesanan {$code} ditolak.";
            case 'siap':
                $this->orders->markReady($order);

                return "Pesanan {$code} ditandai siap.";
            case 'batal':
                $this->orders->cancel($order, $rest ?: null);

                return "Pesanan {$code} dibatalkan; uang mukanya dicatat kembali ke pelanggan.";
            case 'selesai':
                return $this->complete($order);
            default:
                return $this->pay($order, $verb, $rest);
        }
    }

    private function complete(Order $order): string
    {
        $this->orders->complete($order);
        $rest = $order->total - $this->orders->paid($order->refresh());

        return "Pesanan {$order->code} selesai dan tercatat sebagai penjualan."
            .($rest > 0 ? ' Sisa '.Format::rupiah($rest)." jadi piutang {$order->customer_name}." : ' Sudah lunas.');
    }

    /**
     * "dp 20rb qris" or "lunas [qris]": tunai unless a transfer or QRIS is named.
     */
    private function pay(Order $order, string $verb, string $rest): string
    {
        $method = preg_match('/\b(qris|tf|transfer|non\s*tunai|bank|ewallet|gopay|ovo|dana)\b/iu', $rest) === 1 ? PaymentMethod::NonTunai : PaymentMethod::Tunai;
        $left = $order->total - $this->orders->paid($order);
        $amount = $verb === 'lunas' ? $left : Format::parseMoney((string) strtok($rest, ' '));

        if ($amount === null || $amount < 1) {
            return $verb === 'lunas' ? "Pesanan {$order->code} sudah lunas." : "Tulis nominalnya, misalnya \"{$order->code} dp 20rb qris\".";
        }

        $this->orders->recordPayment($order, $amount, $method, source: $this->channel->source());
        $after = $left - $amount;

        return 'Pembayaran '.Format::rupiah($amount)." ({$method->label()}) untuk {$order->code} dicatat."
            .($after > 0 ? ' Sisa '.Format::rupiah($after).'.' : ' Sudah lunas.');
    }
}
