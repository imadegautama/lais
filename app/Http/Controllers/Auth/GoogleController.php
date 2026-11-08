<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Masuk atau daftar dengan Google. Google has checked the email, so an
 * existing account with that email is joined to it, and a new one starts
 * verified. Two-factor still applies: Google never skips the code.
 */
class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->refuse('Sesi masuk dengan Google kedaluwarsa. Coba lagi.');
        } catch (Throwable $exception) {
            // Also a cancelled consent screen: Google returns without a code.
            report($exception);

            return $this->refuse('Masuk dengan Google gagal. Coba lagi, atau masuk dengan email.');
        }

        $email = Str::lower((string) $google->getEmail());

        // Joining by email is only safe when Google vouches for the address.
        $verified = $google instanceof AbstractUser && ($google->getRaw()['email_verified'] ?? false) === true;

        if ($email === '' || ! $verified) {
            return $this->refuse('Email akun Google ini belum terverifikasi. Masuk dengan email dan password.');
        }

        $user = User::query()->where('google_id', $google->getId())->first()
            ?? User::query()->where('email', $email)->first();
        $isNew = $user === null;

        // A random password keeps the column filled; "Lupa password" sets a real one.
        $user ??= User::create([
            'name' => $google->getName() ?: Str::before($email, '@'),
            'email' => $email,
            'password' => Str::random(40),
        ]);

        $user->forceFill(['google_id' => $google->getId()])->save();

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        // Same hand-off as Fortify's RedirectIfTwoFactorAuthenticatable.
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => true]);
            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return $isNew ? redirect()->route('onboarding.show') : redirect()->intended(config('fortify.home'));
    }

    private function refuse(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['google' => $message]);
    }
}
