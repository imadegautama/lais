<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsApp;
use App\Models\User;
use App\Models\WaMessage;
use App\Services\Ai\AssistantUnavailable;
use App\Services\Ai\FormAssistant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Activitylog\Facades\Activity;
use Throwable;

/**
 * Every message to the bot ends here (PRD §4.1). Fixed commands are read
 * without the model; everything else goes to the same FormAssistant as
 * the web's Tanya AI. Replies only ever go back to the owner.
 *
 * @phpstan-import-type Button from Buttons
 */
class Inbox
{
    private const DRAFT_MINUTES = 15;

    private const HELP = "Ceritakan saja yang mau dicatat, seperti ngobrol biasa:\n• jual 3 nasi goreng tunai\n• beli beras 5 kg 70rb\n• ambil 200rb buat rumah\n• atau kirim foto nota belanja\n\nMau tanya juga boleh:\n• untung bulan ini berapa?\n• utang dicatat di mana?\n• apa itu HPP?\n• es teh worth it?\n\nPesanan dari toko online masuk ke sini lengkap dengan tombolnya. Bisa juga diketik, misalnya *terima A012*, *A012 dp 20rb qris*, *A012 lunas*, atau *A012 selesai*.";

    public function __construct(private Linking $linking, private FormAssistant $assistant, private OrderCommands $orderCommands, private BotChannel $channel) {}

    public function handle(WaMessage $message): void
    {
        $payload = $message->payload ?? [];
        $from = Address::from($payload);
        $text = trim((string) $message->body);

        // "LAIS 123456" typed, or "/start 123456" from Telegram's link.
        if (preg_match('/^(?:lais\s*|\/start\s+)(\d{6})$/i', $text, $code) === 1) {
            $linked = $this->linking->redeem($code[1], $from);
            $this->reply($from->replyTo, is_string($linked)
                ? $linked
                : "Halo! Bot sudah terhubung ke *{$linked->currentBusiness()->name}*.\n\n".self::HELP, is_string($linked) ? null : $linked->id);

            return;
        }

        $owner = $this->linking->owner($from);

        if ($owner === null) {
            $this->stranger($from);

            return;
        }

        $message->forceFill(['user_id' => $owner->id])->save();
        // A queue job has no session, so the audit log is told who and through what.
        Context::add('activity_source', $this->channel->source()->value);
        Activity::defaultCauser($owner, fn () => $this->owner($owner, $message, $from, $text));
    }

    /**
     * A linked owner's message: "1" and "2" answer the waiting draft without
     * the model; anything else is read like Tanya AI, building on that draft.
     */
    private function owner(User $owner, WaMessage $message, Address $from, string $text): void
    {
        if (preg_match('/^\/(start|help)$/i', $text) === 1) {
            $this->reply($from->replyTo, self::HELP, $owner->id);

            return;
        }

        $command = $this->orderCommands->handle($owner, $text);

        if ($command !== null) {
            $this->reply($from->replyTo, $command['text'], $owner->id, $command['buttons']);

            return;
        }

        $draft = $this->draft($owner);

        if ($text === '2') {
            Cache::forget(self::draftKey($owner));
            $this->reply($from->replyTo, $draft === null ? 'Belum ada catatan yang menunggu disimpan.' : 'Oke, dibatalkan. Tidak ada yang disimpan.', $owner->id);

            return;
        }

        $photo = null;

        if (! empty($message->payload['image'])) {
            $this->channel->typing($from->replyTo);

            try {
                $photo = $this->channel->downloadImage($message);
            } catch (Throwable $exception) {
                report($exception);
                $this->reply($from->replyTo, 'Fotonya tidak bisa diambil. Coba kirim lagi, atau ketik saja yang mau dicatat.', $owner->id);

                return;
            }
        }

        if ($text === '1' && $draft === null && $photo === null) {
            $this->reply($from->replyTo, 'Belum ada catatan yang menunggu disimpan. Ceritakan saja yang mau dicatat, misalnya "jual 3 nasi goreng tunai".', $owner->id);

            return;
        }

        // Shown until the reply arrives, about five seconds at most; a photo was already a wait of its own.
        $this->channel->typing($from->replyTo);

        try {
            $reply = $this->assistant->reply(
                $owner,
                // "1" is the chat's "simpan"; FormAssistant::confirms() knows the words, not the digit.
                $text === '1' && $photo === null ? 'simpan' : $text,
                null,
                $text === '1' ? [] : $this->history($owner, $message),
                [],
                $draft,
                $photo,
                $this->channel->source(),
            );
        } catch (AssistantUnavailable) {
            // Left out of later history, so a retry is not read as a second transaction.
            $message->forceFill(['payload' => [...($message->payload ?? []), 'lais_failed' => true]])->save();
            $this->reply($from->replyTo, 'Maaf, AI-nya sedang sibuk. Coba kirim lagi sebentar lagi, atau catat lewat web dulu.', $owner->id);

            return;
        }

        $this->keepDraft($owner, $reply, $draft, $photo);
        $answer = Formatter::reply($reply);
        $this->reply($from->replyTo, $answer['text'], $owner->id, $answer['buttons']);
    }

    /**
     * The draft the owner was last shown, for 15 minutes (PRD US-06): the
     * reply's own, a guide's offer, or kept as it was when the reply only
     * asked something back. Saved means gone.
     *
     * @param  array<string, mixed>  $reply  FormAssistant's Reply
     * @param  array{form: string, values: array<string, mixed>, photo?: string|null}|null  $previous
     */
    private function keepDraft(User $owner, array $reply, ?array $previous, ?string $photo = null): void
    {
        $offer = $reply['guide']['offer'] ?? null;
        $source = $reply['values'] !== null && $reply['form'] !== null ? $reply : ($offer !== null && $offer['values'] !== null ? $offer : null);

        if ($reply['saved'] !== null) {
            Cache::forget(self::draftKey($owner));
        } elseif ($source !== null) {
            // A correction of a receipt's draft keeps the receipt, also through registering its goods; products take no proof.
            $keptPhoto = $source['form'] === 'produk' ? null : $photo ?? $previous['photo'] ?? null;
            Cache::put(self::draftKey($owner), array_filter([
                'form' => $source['form'],
                'values' => $source['values'],
                'photo' => $keptPhoto,
            ], fn (mixed $value) => $value !== null), now()->addMinutes(self::DRAFT_MINUTES));
        }
    }

    /**
     * @return array{form: string, values: array<string, mixed>, photo?: string|null}|null
     */
    private function draft(User $owner): ?array
    {
        $draft = Cache::get(self::draftKey($owner));

        return is_array($draft) && is_string($draft['form'] ?? null) && is_array($draft['values'] ?? null) ? $draft : null;
    }

    private static function draftKey(User $owner): string
    {
        return "wa-draft:{$owner->id}";
    }

    /**
     * The last few turns before this one, oldest first, as Tanya AI sends
     * them: only the last 15 minutes, the life of a draft, and never a
     * message the model failed to read. Older chat is another conversation.
     *
     * @return list<array{role: string, text: string}>
     */
    private function history(User $owner, WaMessage $message): array
    {
        return array_values(WaMessage::query()
            ->where('user_id', $owner->id)
            ->where('id', '<', $message->id)
            ->where('created_at', '>=', now()->subMinutes(self::DRAFT_MINUTES))
            ->whereNotNull('body')
            ->latest('id')
            ->limit(10)
            ->get()
            ->reject(fn (WaMessage $turn) => ! empty($turn->payload['lais_failed']))
            ->take(6)
            ->reverse()
            ->map(fn (WaMessage $turn) => [
                'role' => $turn->direction === 'in' ? 'user' : 'assistant',
                'text' => mb_substr((string) $turn->body, 0, 1000) ?: 'Foto nota',
            ])
            ->all());
    }

    /**
     * An unknown sender gets one pointer a day, never a conversation (PRD US-06).
     */
    private function stranger(Address $from): void
    {
        $key = 'wa-stranger:'.($from->phone ?? $from->lid ?? $from->replyTo);

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return;
        }

        RateLimiter::hit($key, 86400);
        $this->reply($from->replyTo, 'Akun ini belum terhubung ke Lais. Daftar di '.route('register').', lalu buka Pengaturan > Bot untuk menghubungkannya.');
    }

    /**
     * @param  list<Button>  $buttons
     */
    private function reply(string $to, string $text, ?int $userId = null, array $buttons = []): void
    {
        SendWhatsApp::dispatch($to, $text, $userId, $buttons);
    }
}
