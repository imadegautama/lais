<?php

namespace App\Services\WhatsApp;

use App\Enums\TransactionSource;
use App\Jobs\ProcessWhatsAppMessage;
use App\Models\WaMessage;
use App\Support\ImageData;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The bot on Telegram's official Bot API (docs/TELEGRAM.md). Messages come
 * in by long polling (lais:telegram), so it runs the same on a laptop and
 * a server. shortcut: one poller per token; use a webhook if volume grows.
 */
class Telegram implements BotChannel
{
    private const API = 'https://api.telegram.org';

    public function send(string $to, string $text): ?string
    {
        $sent = $this->call('sendMessage', [
            'chat_id' => $to,
            // Telegram refuses anything over 4096 characters; a cut message beats none.
            'text' => self::html(mb_substr($text, 0, 4000)),
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
        ]);
        $id = is_array($sent) ? ($sent['message_id'] ?? null) : null;

        return $id === null ? null : (string) $id;
    }

    /**
     * Formatter writes WhatsApp's *bold*; Telegram reads it as HTML, where
     * anything a person typed must be escaped first.
     */
    public static function html(string $text): string
    {
        return (string) preg_replace('/\*([^*\n]+)\*/u', '<b>$1</b>', htmlspecialchars($text, ENT_NOQUOTES));
    }

    /**
     * New updates, waiting up to $wait seconds. Asking with a higher offset
     * confirms the ones before it.
     *
     * @return list<array<string, mixed>>
     */
    public function updates(int $offset, int $wait = 25): array
    {
        $updates = $this->call('getUpdates', ['offset' => $offset, 'timeout' => $wait, 'allowed_updates' => ['message']], $wait + 10);

        return is_array($updates) ? array_values(array_filter($updates, 'is_array')) : [];
    }

    /**
     * Keep one update, once, and hand it to the same job as WhatsApp.
     * Only a person's own chat with the bot, with something to read.
     *
     * @param  array<string, mixed>  $update
     */
    public function receive(array $update): void
    {
        $message = is_array($update['message'] ?? null) ? $update['message'] : [];
        $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
        $photos = is_array($message['photo'] ?? null) ? $message['photo'] : [];
        $body = trim((string) ($message['text'] ?? $message['caption'] ?? ''));

        if (($chat['type'] ?? null) !== 'private' || ! isset($update['update_id'], $chat['id']) || ($body === '' && $photos === [])) {
            return;
        }

        $chatId = (string) $chat['id'];
        $stored = WaMessage::query()->firstOrCreate(['message_id' => "tg:{$update['update_id']}"], [
            'direction' => 'in',
            'phone' => $chatId,
            'body' => mb_substr($body, 0, 2000),
            // Sizes come smallest first.
            'payload' => ['telegram_chat_id' => $chatId, 'image' => Arr::last($photos)['file_id'] ?? null],
        ]);

        if ($stored->wasRecentlyCreated) {
            ProcessWhatsAppMessage::dispatch($stored);
        }
    }

    public function downloadImage(WaMessage $message): string
    {
        $file = $this->call('getFile', ['file_id' => $message->payload['image'] ?? '']);
        $path = is_array($file) ? (string) ($file['file_path'] ?? '') : '';
        $url = self::API.'/file/bot'.config('services.telegram.token').'/'.$path;

        return ImageData::fromBytes($this->safely('getFile', fn () => Http::timeout(30)->get($url)->throw())->body());
    }

    public function source(): TransactionSource
    {
        return TransactionSource::Telegram;
    }

    public function name(): string
    {
        return 'Telegram';
    }

    public function contact(): ?string
    {
        $username = (string) config('services.telegram.username');

        return $username === '' || (string) config('services.telegram.token') === '' ? null : "@{$username}";
    }

    public function linkUrl(string $code): ?string
    {
        return $this->contact() === null ? null : 'https://t.me/'.config('services.telegram.username').'?start='.$code;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function call(string $method, array $data, int $timeout = 15): mixed
    {
        $url = self::API.'/bot'.config('services.telegram.token').'/'.$method;

        return $this->safely($method, fn () => Http::timeout($timeout)->acceptJson()->post($url, $data)->throw())->json('result');
    }

    /**
     * The token is part of every URL, and connection errors quote the URL,
     * so failures are rethrown in words that keep it out of logs and failed_jobs.
     *
     * @param  Closure(): Response  $request
     */
    private function safely(string $method, Closure $request): Response
    {
        try {
            return $request();
        } catch (RequestException $exception) {
            throw new RuntimeException("Telegram {$method}: ".$exception->response->status().' '.$exception->response->json('description'));
        } catch (ConnectionException) {
            throw new RuntimeException("Telegram {$method}: tidak tersambung.");
        }
    }
}
