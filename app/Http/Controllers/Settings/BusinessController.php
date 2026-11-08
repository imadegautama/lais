<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BusinessUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan > Usaha (UI-PAGES C20). The shop's switches, QRIS, and number
 * stay on Pesanan (DESIGN.md #15); this page links there.
 */
class BusinessController extends Controller
{
    public function edit(Request $request): Response
    {
        $business = $request->user()->currentBusiness();

        return Inertia::render('settings/business', [
            'business' => [
                'name' => $business->name,
                'category' => $business->category,
                'shopUrl' => route('shop.show', $business->slug),
                'open' => $business->accepting_orders,
            ],
        ]);
    }

    public function update(BusinessUpdateRequest $request): RedirectResponse
    {
        $request->user()->currentBusiness()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data usaha disimpan.']);

        return back();
    }
}
