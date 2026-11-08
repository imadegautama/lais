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
 *
 * @phpstan-import-type Button from Buttons
 */
class Telegram implements BotChannel
{
    private const API = 'https://api.telegram.org';

    /** Telegram's limit on what a button sends back. */
    private const CALLBACK_BYTES = 64;

    /** Buttons this short share a row, like Simpan and Batal. */
    private const SHORT_LABEL = 16;

    /**
     * @param  list<Button>  $buttons
     */
    public function send(string $to, string $text, array $buttons = []): ?string
    {
        [$keyboard, $written] = self::keyboard($buttons);
        $sent = $this->call('sendMessage', [
            'chat_id' => $to,
            // Telegram refuses anything over 4096 characters; a cut message beats none.
            'text' => self::html(mb_substr(Buttons::asText($text, $written), 0, 4000)),
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
            ...($keyboard === [] ? [] : ['reply_markup' => ['inline_keyboard' => $keyboard]]),
        ]);
        $id = is_array($sent) ? ($sent['message_id'] ?? null) : null;

        return $id === null ? null : (string) $id;
    }

    public function typing(string $to): void
    {
        $this->quietly('sendChatAction', ['chat_id' => $to, 'action' => 'typing']);
    }

    /**
     * Buttons as Telegram's inline keyboard, and those it cannot show, to be
     * written out: a link to a plain http address (Telegram refuses it, so on
     * a laptop links stay text) or words too long to send back.
     *
     * @param  list<Button>  $buttons
     * @return array{0: list<list<array<string, string>>>, 1: list<Button>}
     */
    public static function keyboard(array $buttons): array
    {
        $short = [];
        $rows = [];
        $written = [];

        foreach ($buttons as $button) {
            if (isset($button['url'])) {
                if (str_starts_with($button['url'], 'https://')) {
                    $rows[] = [['text' => $button['label'], 'url' => $button['url']]];
                } else {
                    $written[] = $button;
                }

                continue;
            }

            // d: answers the draft on screen, so only the latest message counts; c: names its own order.
            $data = (($button['command'] ?? false) ? 'c:' : 'd:').($button['send'] ?? '');

            if (strlen($data) > self::CALLBACK_BYTES) {
                $written[] = $button;
            } elseif (mb_strlen($button['label']) <= self::SHORT_LABEL) {
                $short[] = ['text' => $button['label'], 'callback_data' => $data];
            } else {
                $rows[] = [['text' => $button['label'], 'callback_data' => $data]];
            }
        }

        return [$short === [] ? $rows : [$short, ...$rows], $written];
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
        $updates = $this->call('getUpdates', ['offset' => $offset, 'timeout' => $wait, 'allowed_updates' => ['message', 'callback_query']], $wait + 10);

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
        if (is_array($update['callback_query'] ?? null)) {
            $this->pressed($update['callback_query']);

            return;
        }

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

    /**
     * A button pressed: read as if the owner typed its words, once per
     * message. A draft's button on an older message would save whatever
     * draft is open now, so it only answers with a note.
     *
     * @param  array<string, mixed>  $query
     */
    private function pressed(array $query): void
    {
        $message = is_array($query['message'] ?? null) ? $query['message'] : [];
        $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];

        if (($chat['type'] ?? null) !== 'private' || ! isset($query['id'], $chat['id'], $message['message_id'])
            || preg_match('/^([dc]):(.+)$/su', (string) ($query['data'] ?? ''), $pressed) !== 1) {
            return;
        }

        $chatId = (string) $chat['id'];
        $latest = WaMessage::query()->where('direction', 'out')->where('phone', $chatId)->latest('id')->value('payload');
        $stale = $pressed[1] === 'd' && (string) ($latest['gowa_id'] ?? '') !== (string) $message['message_id'];

        $this->quietly('answerCallbackQuery', ['callback_query_id' => $query['id'], ...($stale ? ['text' => 'Pilihan ini sudah lewat. Pakai pesan terbaru, atau ketik saja.'] : [])]);
        // Pressed once, gone: the same choice cannot be sent twice.
        $this->quietly('editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => $message['message_id'], 'reply_markup' => ['inline_keyboard' => []]]);

        if ($stale) {
            return;
        }

        $stored = WaMessage::query()->firstOrCreate(['message_id' => "tg:{$chatId}:{$message['message_id']}:tombol"], [
            'direction' => 'in',
            'phone' => $chatId,
            'body' => mb_substr($pressed[2], 0, 2000),
            'payload' => ['telegram_chat_id' => $chatId, 'image' => null],
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
     * A call nothing waits on (typing, a button's spinner): a failure is
     * reported, never thrown, so the poller and the reply go on.
     *
     * @param  array<string, mixed>  $data
     */
    private function quietly(string $method, array $data): void
    {
        try {
            $this->call($method, $data, 5);
        } catch (RuntimeException $exception) {
            report($exception);
        }
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
