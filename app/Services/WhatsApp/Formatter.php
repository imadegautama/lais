<?php

namespace App\Services\WhatsApp;

use App\Support\Format;

/**
 * FormAssistant's reply as a chat message: the draft as lines, what is
 * missing, and buttons to answer or open a page (Buttons). Uses WhatsApp's
 * own *bold*, nothing else; Telegram turns it into HTML.
 *
 * @phpstan-import-type Reply from \App\Services\Ai\FormAssistant
 * @phpstan-import-type Card from \App\Services\Ai\FormAssistant
 * @phpstan-import-type Button from Buttons
 */
class Formatter
{
    /** Under a draft that is ready; the buttons (or "Balas 1/2") say how to save it. */
    public const READY = 'Sudah benar? Kalau ada yang salah, ketik saja koreksinya.';

    /** Under a draft that still lacks something. */
    public const INCOMPLETE = 'Ketik kekurangannya di sini, nanti saya lengkapi.';

    /**
     * A draft reads like a receipt first, then what to do with it. The web's
     * own question ("Simpan catatan ini?", "Masih ada yang kurang…") becomes
     * the closing line and the buttons here.
     *
     * @param  Reply  $reply
     * @return array{text: string, buttons: list<Button>}
     */
    public static function reply(array $reply): array
    {
        $offer = $reply['guide']['offer'] ?? null;
        $draft = $reply['card'] !== null;
        // A guide's draft is offered in the chat the way the web shows it as a card to open.
        $card = $reply['card'] ?? $offer['card'] ?? null;
        $missing = $draft ? $reply['missing'] : ($offer['missing'] ?? []);
        $confirm = ($draft ? $reply['confirm'] : (bool) ($offer['confirm'] ?? false)) && $reply['saved'] === null;
        // Anything else the assistant said about the draft (a refusal, a question) stays.
        $said = $draft && preg_match('/^(Simpan .+\?|Masih ada yang kurang\..*)$/u', $reply['reply']) === 1 ? '' : $reply['reply'];
        $needs = $reply['guide'] !== null && $reply['guide']['needs'] !== [] ? "Yang perlu disiapkan:\n".self::bullets($reply['guide']['needs']) : '';
        $lacking = $missing === [] ? '' : "Masih kurang:\n".self::bullets($missing);

        $parts = $draft
            ? [self::card($card), $lacking, $said]
            : [$said, $needs, $card === null ? '' : self::card($card), $lacking];
        $parts[] = match (true) {
            $confirm => self::READY,
            $card !== null && $missing !== [] && (! $draft || $said === '') => self::INCOMPLETE,
            default => '',
        };

        $buttons = [...Buttons::options($reply['options']), ...($confirm ? Buttons::confirm() : [])];

        foreach ([$reply['saved'], $reply['link']] as $link) {
            if ($link !== null) {
                $buttons[] = Buttons::link($link);
            }
        }

        return ['text' => implode("\n\n", array_filter($parts, fn (string $part) => trim($part) !== '')), 'buttons' => $buttons];
    }

    /**
     * @param  Card  $card
     */
    public static function card(array $card): string
    {
        $lines = ["*{$card['title']}*"];

        foreach ($card['lines'] as $line) {
            $qty = $line['qty'] === null ? '' : Format::qty($line['qty']).($line['unit'] ? " {$line['unit']}" : '').' ';
            $price = $line['price'] !== null && $line['qty'] !== null && $line['qty'] != 1 ? ' × '.Format::rupiah($line['price']) : '';
            $amount = $line['amount'] === null ? '' : ' = '.Format::rupiah($line['amount']);
            $lines[] = "• {$qty}{$line['name']}{$price}{$amount}";
        }

        if ($card['total'] !== null) {
            $lines[] = "{$card['total']['label']}: *".Format::rupiah($card['total']['amount']).'*';
        }

        foreach ($card['notes'] as $note) {
            $lines[] = $note;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $items
     */
    private static function bullets(array $items): string
    {
        return implode("\n", array_map(fn (string $item) => "• {$item}", $items));
    }
}
