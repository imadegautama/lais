<?php

namespace App\Services\WhatsApp;

use App\Enums\TransactionSource;
use App\Models\WaMessage;
use App\Support\ImageData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The bot's WhatsApp number, through GOWA's REST API (docs/WHATSAPP.md).
 * Sends only to the owner: the bot never messages a customer (PRD §5).
 *
 * @phpstan-import-type Button from Buttons
 */
class Gowa implements BotChannel
{
    /**
     * @param  string  $to  62… for a linked owner, or the JID a message came from
     * @param  list<Button>  $buttons  written out: WhatsApp gets no buttons through GOWA
     * @return string|null GOWA's id for the sent message
     */
    public function send(string $to, string $text, array $buttons = []): ?string
    {
        $id = $this->http()->post('/send/message', ['phone' => $to, 'message' => Buttons::asText($text, $buttons)])->throw()->json('results.message_id');

        return is_string($id) ? $id : null;
    }

    /**
     * A photo the owner sent, as a JPEG data URI for the assistant (PRD US-16).
     * GOWA keeps the file on its side and serves it after a download call.
     */
    public function downloadImage(WaMessage $message): string
    {
        $results = $this->http()->get('/message/'.rawurlencode((string) $message->message_id).'/download', ['phone' => $message->phone])->throw()->json('results');
        $url = is_array($results) ? ($results['file_url'] ?? null) : null;
        $path = is_array($results) ? ($results['file_path'] ?? null) : null;

        if (! is_string($url) || $url === '') {
            $url = rtrim((string) config('services.gowa.url'), '/').'/'.ltrim((string) $path, '/');
        }

        return ImageData::fromBytes($this->http()->get($url)->throw()->body());
    }

    /**
     * shortcut: no typing indicator on WhatsApp; add GOWA's chat presence if the bot moves back from Telegram.
     */
    public function typing(string $to): void {}

    public function source(): TransactionSource
    {
        return TransactionSource::Wa;
    }

    public function name(): string
    {
        return 'WhatsApp';
    }

    public function contact(): ?string
    {
        $number = (string) config('services.gowa.bot_number');

        return $number === '' ? null : "+{$number}";
    }

    public function linkUrl(string $code): ?string
    {
        return $this->contact() === null ? null : 'https://wa.me/'.config('services.gowa.bot_number').'?text='.rawurlencode("LAIS {$code}");
    }

    private function http(): PendingRequest
    {
        [$user, $password] = array_pad(explode(':', (string) config('services.gowa.basic_auth'), 2), 2, '');

        return Http::baseUrl(rtrim((string) config('services.gowa.url'), '/'))
            ->withBasicAuth($user, $password)
            ->withHeaders(['X-Device-Id' => (string) config('services.gowa.device_id')])
            ->acceptJson()
            ->timeout(15);
    }
}
