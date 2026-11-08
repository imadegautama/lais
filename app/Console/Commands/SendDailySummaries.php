<?php

namespace App\Console\Commands;

use App\Jobs\SendWhatsApp;
use App\Models\Business;
use App\Services\WhatsApp\DailySummary;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Sends the evening summary (PRD M8) to every owner who linked the bot
 * and left it on. Scheduled at 21.00 app time (routes/console.php); --usaha
 * sends one now, for a demo, whatever the switch says.
 */
#[Signature('lais:ringkasan {--usaha= : Kirim hanya ke usaha ini, sekarang}')]
#[Description('Kirim ringkasan harian lewat bot')]
class SendDailySummaries extends Command
{
    public function handle(DailySummary $summary): int
    {
        $only = $this->option('usaha');
        $sent = 0;

        Business::query()
            ->with('user')
            ->when($only, fn ($query) => $query->whereKey($only), fn ($query) => $query->where('daily_summary', true))
            ->whereHas('user', fn ($query) => $query->whereNotNull('whatsapp_linked_at'))
            ->each(function (Business $business) use ($summary, &$sent) {
                SendWhatsApp::dispatch((string) $business->user->whatsappAddress(), $summary->for($business), $business->user_id);
                $sent++;
            });

        $this->info("Ringkasan dikirim ke {$sent} usaha.");

        return self::SUCCESS;
    }
}
