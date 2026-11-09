<?php

namespace App\Http\Controllers;

use App\Services\DemoAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * "Coba demo": signs the visitor in to an account of their own with the
 * Warung Bu Rina books (App\Services\DemoAccounts), no sign-up needed.
 */
class DemoController extends Controller
{
    public function __invoke(Request $request, DemoAccounts $demo): RedirectResponse
    {
        if ($request->user() === null) {
            Auth::login($demo->claim(), remember: true);
            $request->session()->regenerate();
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Selamat datang di akun demo pribadimu. Semua bisa dicoba.']);
        }

        return to_route('dashboard');
    }
}
