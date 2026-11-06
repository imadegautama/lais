<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\BotChannel;
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
}
