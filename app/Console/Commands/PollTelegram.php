<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\Telegram;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Receives the bot's Telegram messages. Runs all the time next to
 * queue:work (docs/TELEGRAM.md); only one per bot token.
 */
#[Signature('lais:telegram {--once : Ambil yang menunggu lalu berhenti}')]
#[Description('Terima pesan bot Telegram')]
class PollTelegram extends Command
{
    public function handle(Telegram $telegram): int
    {
        if ($telegram->contact() === null) {
            $this->error('Isi TELEGRAM_BOT_TOKEN dan TELEGRAM_BOT_USERNAME dulu (docs/TELEGRAM.md).');

            return self::FAILURE;
        }

        $once = (bool) $this->option('once');
        $offset = 0;

        if (! $once) {
            $this->info("Bot {$telegram->contact()} aktif, menunggu pesan. Biarkan terminal ini terbuka; Ctrl+C untuk berhenti.");
        }

        do {
            try {
                $updates = $telegram->updates($offset, $once ? 0 : 25);
            } catch (RuntimeException $exception) {
                // A network blip must not end the bot; Telegram keeps the updates until asked again.
                $this->warn($exception->getMessage());
                $updates = [];

                if (! $once) {
                    sleep(3);
                }
            }

            foreach ($updates as $update) {
                $telegram->receive($update);
                $offset = (int) ($update['update_id'] ?? $offset) + 1;
            }
        } while (! $once || $updates !== []);

        return self::SUCCESS;
    }
}
