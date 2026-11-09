<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsApp;
use App\Services\Push;
use App\Services\WhatsApp\BotChannel;
use App\Services\WhatsApp\DailySummary;
use App\Services\WhatsApp\Linking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan > Bot (UI-PAGES C20): link the owner's chat account by sending
 * a code to the bot, and switch the evening summary on or off.
 */
class WhatsAppController extends Controller
{
    public function edit(Request $request, Linking $linking, BotChannel $channel): Response
    {
        $user = $request->user();
        $pending = $user->whatsapp_linked_at === null ? $linking->pending($user) : null;

        return Inertia::render('settings/whatsapp', [
            'channel' => $channel->name(),
            'contact' => $channel->contact(),
            'linked' => $user->whatsapp_linked_at === null ? null : [
                'phone' => $user->whatsapp,
                'at' => $user->whatsapp_linked_at->toIso8601String(),
            ],
            'pending' => $pending === null ? null : [...$pending, 'url' => $channel->linkUrl($pending['code'])],
            'dailySummary' => $user->currentBusiness()->daily_summary,
            // Web push for new orders; null hides the card until the VAPID keys are set.
            'pushKey' => Push::ready() ? (string) config('services.webpush.public_key') : null,
        ]);
    }

    public function code(Request $request, Linking $linking): RedirectResponse
    {
        $linking->issue($request->user());

        return back();
    }

    public function unlink(Request $request, Linking $linking): RedirectResponse
    {
        $linking->unlink($request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Koneksi bot diputuskan. Pesan dari akun itu tidak lagi diterima.']);

        return back();
    }

    public function summary(Request $request): RedirectResponse
    {
        $on = $request->validate(['daily_summary' => ['required', 'boolean']])['daily_summary'];
        $request->user()->currentBusiness()->update(['daily_summary' => $on]);

        return back();
    }

    /**
     * Today's summary to the owner's chat now, the same as /ringkasan: no waiting until 21.00.
     */
    public function sendSummary(Request $request, DailySummary $summary, BotChannel $channel): RedirectResponse
    {
        $user = $request->user();
        $address = $user->whatsappAddress();

        if ($address === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "Hubungkan bot {$channel->name()} dulu."]);

            return back();
        }

        SendWhatsApp::dispatch($address, $summary->for($user->currentBusiness()), $user->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Ringkasan hari ini dikirim ke {$channel->name()}."]);

        return back();
    }
}
