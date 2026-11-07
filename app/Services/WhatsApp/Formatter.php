<?php

namespace App\Services\WhatsApp;

use App\Support\Format;

/**
 * FormAssistant's reply as WhatsApp text: the draft as lines, what is
 * missing, and how to answer. Links are made absolute so they open from
 * the chat. Uses WhatsApp's own *bold*, nothing else.
 *
 * @phpstan-import-type Reply from \App\Services\Ai\FormAssistant
 * @phpstan-import-type Card from \App\Services\Ai\FormAssistant
 */
class Formatter
{
    public const CONFIRM = 'Balas *1* untuk simpan, *2* untuk batal.';

    /**
     * @param  Reply  $reply
     */
    public static function reply(array $reply): string
    {
        $offer = $reply['guide']['offer'] ?? null;
        $parts = [$reply['reply']];

        if ($reply['guide'] !== null && $reply['guide']['needs'] !== []) {
            $parts[] = "Yang perlu disiapkan:\n".self::bullets($reply['guide']['needs']);
        }

        // A guide's draft is offered in the chat the way the web shows it as a card to open.
        $card = $reply['card'] ?? $offer['card'] ?? null;
        $missing = $reply['card'] !== null ? $reply['missing'] : ($offer['missing'] ?? []);
        $confirm = $reply['card'] !== null ? $reply['confirm'] : (bool) ($offer['confirm'] ?? false);

        if ($card !== null) {
            $parts[] = self::card($card);
        }

        if ($missing !== []) {
            $parts[] = "Yang masih kurang:\n".self::bullets($missing);
        }

        if ($reply['options'] !== []) {
            $parts[] = 'Pilihan: '.implode(' / ', $reply['options']);
        }

        if ($confirm && $reply['saved'] === null) {
            $parts[] = self::CONFIRM;
        }

        foreach ([$reply['saved'], $reply['link']] as $link) {
            if ($link !== null) {
                $parts[] = "{$link['label']}: ".url($link['href']);
            }
        }

        return implode("\n\n", array_filter($parts, fn (string $part) => trim($part) !== ''));
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
