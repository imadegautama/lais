<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\PushSubscriptionRequest;
use App\Models\PushSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Pengaturan > Bot, "Notifikasi di HP ini": turns web push for new orders on
 * or off for the browser the owner is on.
 */
class PushSubscriptionController extends Controller
{
    public function store(PushSubscriptionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // A browser that moves to another account follows it; the endpoint is the browser's own.
        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => PushSubscription::hash($data['endpoint'])],
            ['user_id' => $user->id, 'endpoint' => $data['endpoint'], 'public_key' => $data['keys']['p256dh'], 'auth_token' => $data['keys']['auth']],
        );

        // Old phones fall off, so a long list never builds up.
        $user->pushSubscriptions()->latest('id')->skip(PushSubscription::PER_USER)->take(PHP_INT_MAX)->get()->each->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Notifikasi pesanan baru aktif di perangkat ini.']);

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $endpoint = (string) $request->validate(['endpoint' => ['required', 'string', 'max:2000']])['endpoint'];
        $request->user()->pushSubscriptions()->where('endpoint_hash', PushSubscription::hash($endpoint))->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Notifikasi dimatikan di perangkat ini.']);

        return back();
    }
}
