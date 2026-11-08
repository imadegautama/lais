<?php

namespace App\Services\WhatsApp;

/**
 * Buttons under a bot message, the same for every chat app. A button either
 * sends a word back as if the owner typed it (`send`; `command` when it holds
 * an order code and so stays valid on an older message) or opens a page
 * (`url`). Telegram shows them; WhatsApp, and the conversation log, read them
 * as text through asText(), so typing the word always works too.
 *
 * @phpstan-type Button array{label: string, send?: string, url?: string, command?: bool}
 */
final class Buttons
{
    /**
     * Save or drop the waiting draft: the same as typing 1 or 2.
     *
     * @return list<Button>
     */
    public static function confirm(): array
    {
        return [['label' => 'Simpan', 'send' => '1'], ['label' => 'Batal', 'send' => '2']];
    }

    /**
     * Choices the assistant offered; each sends its own words.
     *
     * @param  list<string>  $options
     * @return list<Button>
     */
    public static function options(array $options): array
    {
        return array_map(fn (string $option) => ['label' => $option, 'send' => $option], $options);
    }

    /**
     * An order command, valid on any message because it names the order.
     *
     * @return Button
     */
    public static function command(string $label, string $command): array
    {
        return ['label' => $label, 'send' => $command, 'command' => true];
    }

    /**
     * A page in Lais, made absolute so it opens from the chat.
     *
     * @param  array{label: string, href: string}  $link
     * @return Button
     */
    public static function link(array $link): array
    {
        return ['label' => $link['label'], 'url' => url($link['href'])];
    }

    /**
     * The message with its buttons written out, for a chat without buttons.
     *
     * @param  list<Button>  $buttons
     */
    public static function asText(string $text, array $buttons): string
    {
        $sends = array_values(array_filter($buttons, fn (array $button) => isset($button['send'])));
        $parts = [$text];

        if ($sends !== []) {
            // A choice is typed as it reads; anything else by its word.
            $parts[] = array_filter($sends, fn (array $button) => $button['send'] !== $button['label']) === []
                ? 'Pilihan: '.implode(' / ', array_column($sends, 'label'))
                : 'Balas '.implode(', ', array_map(fn (array $button) => '*'.$button['send'].'* untuk '.mb_strtolower($button['label']), $sends)).'.';
        }

        foreach ($buttons as $button) {
            if (isset($button['url'])) {
                $parts[] = "{$button['label']}: {$button['url']}";
            }
        }

        return implode("\n\n", array_filter($parts, fn (string $part) => trim($part) !== ''));
    }
}
