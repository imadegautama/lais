<?php

namespace App\Services;

use App\Enums\MoneyAccountKind;
use App\Enums\OnlinePaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TransactionSource;
use App\Jobs\SendWhatsApp;
use App\Models\Business;
use App\Models\MoneyAccount;
use App\Models\OnlinePayment;
use App\Models\Order;
use App\Support\OrderMessages;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Bayar online through DOKU Checkout (sandbox for the contest): one platform
 * account, a DOKU payment page per order, and DOKU's HTTP notification to
 * settle it. Money that comes in is uang muka through Orders::recordPayment,
 * into the business's "Saldo DOKU" rekening. Checkout has no refund API, so
 * money for a refused order is returned by hand in DOKU's back office.
 *
 * Signing (developers.doku.com, non-SNAP): HMAC-SHA256 with the Secret Key
 * over Client-Id, Request-Id, Request-Timestamp, Request-Target and, when
 * there is a body, its base64 SHA-256 Digest, one per line.
 */
class Doku
{
    public const ACCOUNT_NAME = 'Saldo DOKU';

    public function __construct(private Orders $orders) {}

    public static function ready(): bool
    {
        return filled(config('services.doku.client_id')) && filled(config('services.doku.secret_key'));
    }

    /** The shop offers paying online now: the owner turned it on and the keys are set. */
    public static function offeredBy(Business $business): bool
    {
        return $business->online_payment && self::ready();
    }

    /**
     * A DOKU payment page for what is still unpaid. A page still open for a
     * while is reused, so pressing "Bayar online" twice opens one invoice.
     *
     * @throws ConnectionException|RequestException when DOKU cannot be reached or refuses
     */
    public function checkout(Order $order): OnlinePayment
    {
        $open = $order->onlinePayments()
            ->where('online_payments.status', OnlinePaymentStatus::Menunggu)
            ->where('online_payments.expires_at', '>', now()->addMinutes(5))
            ->latest('id')
            ->first();

        if ($open !== null) {
            return $open;
        }

        $amount = $order->total - $this->orders->paid($order);
        // Letters and digits only, at most 30 characters (DOKU, for every channel).
        $invoice = 'LAIS'.$order->id.Str::upper(Str::random(8));
        // Marked, so the nota knows the customer is back from paying and checks right away.
        $nota = route('shop.order', [$order->token, 'dari' => 'doku']);

        $page = $this->send('POST', '/checkout/v1/payment', [
            'order' => [
                'amount' => $amount,
                'invoice_number' => $invoice,
                'currency' => 'IDR',
                'callback_url' => $nota,
                'callback_url_result' => $nota,
                'auto_redirect' => true,
            ],
            'payment' => ['payment_due_date' => 60],
            'customer' => ['name' => $order->customer_name, 'phone' => $order->customer_phone],
        ])->throw()->json('response.payment');

        return $order->onlinePayments()->create([
            'invoice_number' => $invoice,
            'amount' => $amount,
            'url' => $page['url'],
            'status' => OnlinePaymentStatus::Menunggu,
            // DOKU writes it in UTC+7; stored in the app's own zone like every other time.
            'expires_at' => CarbonImmutable::createFromFormat('YmdHis', $page['expired_date'], 'Asia/Jakarta')->setTimezone(config('app.timezone')),
        ]);
    }

    /**
     * Ask DOKU about a page whose notification has not come (it is late, or
     * the server cannot be reached, as on a laptop). Errors wait for the next look.
     */
    public function refresh(OnlinePayment $payment): void
    {
        try {
            $response = $this->send('GET', '/orders/v1/status/'.$payment->invoice_number, timeout: 5);
        } catch (ConnectionException) {
            return;
        }

        if ($response->successful()) {
            $this->apply($payment, (string) $response->json('transaction.status'), (int) $response->json('order.amount'), $response->json('channel.id'));
        }
    }

    /**
     * DOKU's word on a payment, from the notification or Check Status.
     */
    public function apply(OnlinePayment $payment, string $status, int $amount, ?string $channel): void
    {
        match ($status) {
            'SUCCESS' => $this->settle($payment, $amount, $channel),
            'EXPIRED' => $this->close($payment, OnlinePaymentStatus::Kedaluwarsa),
            'FAILED' => $this->close($payment, OnlinePaymentStatus::Gagal),
            default => null,
        };
    }

    /**
     * Is this notification really from DOKU: the same signature over the raw
     * body, with the notification's own path as Request-Target.
     */
    public function verify(Request $request): bool
    {
        $clientId = (string) $request->header('Client-Id');
        $expected = self::signature($clientId, (string) $request->header('Request-Id'), (string) $request->header('Request-Timestamp'), '/'.$request->path(), $request->getContent());

        return self::ready() && $clientId === config('services.doku.client_id') && hash_equals($expected, (string) $request->header('Signature'));
    }

    public static function signature(string $clientId, string $requestId, string $timestamp, string $target, ?string $body): string
    {
        $lines = ["Client-Id:{$clientId}", "Request-Id:{$requestId}", "Request-Timestamp:{$timestamp}", "Request-Target:{$target}"];

        if ($body !== null) {
            $lines[] = 'Digest:'.base64_encode(hash('sha256', $body, true));
        }

        return 'HMACSHA256='.base64_encode(hash_hmac('sha256', implode("\n", $lines), (string) config('services.doku.secret_key'), true));
    }

    /**
     * The page was paid: once only, however often DOKU says so. The money
     * becomes uang muka (a waiting order is accepted, since it is paid for and
     * its stock is held). Money the order can no longer take, because it was
     * refused or already paid meanwhile, stays out of the books and the owner
     * is told to return it.
     */
    private function settle(OnlinePayment $payment, int $amount, ?string $channel): void
    {
        $settled = DB::transaction(function () use ($payment, $amount, $channel) {
            $payment = OnlinePayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === OnlinePaymentStatus::Lunas) {
                return null;
            }

            if ($amount !== $payment->amount) {
                report(new RuntimeException("DOKU {$payment->invoice_number}: paid {$amount}, asked {$payment->amount}."));

                return null;
            }

            $payment->update(['status' => OnlinePaymentStatus::Lunas, 'channel' => $channel, 'paid_at' => now()]);
            $order = $payment->order()->with(['business', 'lines'])->firstOrFail();
            $booked = 0;

            if ($order->status !== OrderStatus::Ditolak) {
                if ($order->status === OrderStatus::Menunggu) {
                    $this->orders->accept($order);
                }

                $booked = min($amount, $order->total - $this->orders->paid($order));

                if ($booked > 0) {
                    $this->orders->recordPayment($order, $booked, PaymentMethod::NonTunai, $this->account($order->business)->id, TransactionSource::Toko);
                }
            }

            return [$order, $booked];
        });

        if ($settled === null) {
            return;
        }

        [$order, $booked] = $settled;
        $owner = $order->business->user;

        if ($owner->whatsappAddress() !== null) {
            SendWhatsApp::dispatch($owner->whatsappAddress(), OrderMessages::paidOnline($order, $amount, $booked), $owner->id);
        }
    }

    private function close(OnlinePayment $payment, OnlinePaymentStatus $status): void
    {
        OnlinePayment::query()->whereKey($payment->id)->where('online_payments.status', OnlinePaymentStatus::Menunggu)->update(['status' => $status]);
    }

    /** Where DOKU money sits until it is settled to the bank: one non-cash rekening per business. */
    private function account(Business $business): MoneyAccount
    {
        return $business->moneyAccounts()->firstOrCreate(['name' => self::ACCOUNT_NAME], ['kind' => MoneyAccountKind::NonTunai]);
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function send(string $method, string $target, ?array $body = null, int $timeout = 10): Response
    {
        $clientId = (string) config('services.doku.client_id');
        $requestId = (string) Str::uuid();
        $timestamp = now('UTC')->format('Y-m-d\TH:i:s\Z');
        // Signed and sent as the very same bytes, since the Digest covers them.
        $json = $body === null ? null : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $request = Http::baseUrl((string) config('services.doku.base_url'))
            ->timeout($timeout)
            ->acceptJson()
            ->withHeaders([
                'Client-Id' => $clientId,
                'Request-Id' => $requestId,
                'Request-Timestamp' => $timestamp,
                'Signature' => self::signature($clientId, $requestId, $timestamp, $target, $json),
            ]);

        return $json === null ? $request->get($target) : $request->withBody($json, 'application/json')->send($method, $target);
    }
}
