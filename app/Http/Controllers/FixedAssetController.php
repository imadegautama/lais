<?php

namespace App\Http\Controllers;

use App\Models\FixedAsset;
use App\Services\Depreciation;
use App\Services\ReportStory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aset tetap (SAK EMKM Bab 11): what the peralatan cost, what has been
 * depreciated, and what is left. The owner sets the useful life.
 */
class FixedAssetController extends Controller
{
    public function index(Request $request, Depreciation $depreciation, ReportStory $story): Response
    {
        $business = $request->user()->currentBusiness();
        $assets = $depreciation->book($business);

        return Inertia::render('reports/fixed-assets', [
            'business' => ['name' => $business->name],
            'story' => $story->assets($assets),
            'assets' => $assets,
        ]);
    }

    /**
     * Sets the useful life and catches up on the months already ended. Once
     * a month is posted the life stays, so earlier reports never change.
     */
    public function update(Request $request, FixedAsset $fixedAsset, Depreciation $depreciation): RedirectResponse
    {
        Gate::authorize('manage', $fixedAsset);

        $months = (int) $request->validate(
            ['useful_life_months' => ['required', 'integer', 'min:1', 'max:600']],
            ['useful_life_months.required' => 'Isi umur pakainya.', 'useful_life_months.min' => 'Umur pakai paling sedikit 1 bulan.', 'useful_life_months.max' => 'Umur pakai paling lama 50 tahun.'],
        )['useful_life_months'];

        if ($fixedAsset->depreciated_through !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "Umur {$fixedAsset->name} tidak bisa diubah karena sudah disusutkan."]);

            return back();
        }

        $fixedAsset->update(['useful_life_months' => $months]);
        $depreciation->run(business: $fixedAsset->business);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Umur pakai {$fixedAsset->name} disimpan."]);

        return back();
    }
}
