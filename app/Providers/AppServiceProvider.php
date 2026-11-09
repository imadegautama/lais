<?php

namespace App\Providers;

use App\Models\User;
use App\Services\WhatsApp\BotChannel;
use App\Services\WhatsApp\Gowa;
use App\Services\WhatsApp\Telegram;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\Mime\Email;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BotChannel::class, fn ($app) => $app->make(config('services.bot.channel') === 'whatsapp' ? Gowa::class : Telegram::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthEmails();
    }

    /**
     * A mail message carrying the Lais logo for the header (cid:lais-logo).
     */
    public static function laisMail(): MailMessage
    {
        return (new MailMessage)->withSymfonyMessage(
            fn (Email $email) => $email->embedFromPath(public_path('images/email/lais-wordmark.png'), 'lais-logo', 'image/png'),
        );
    }

    /**
     * The verification and password reset emails in Lais's own words, in
     * Indonesian; their look comes from the mail theme (config/mail.php).
     */
    protected function configureAuthEmails(): void
    {
        VerifyEmail::toMailUsing(fn (User $user, string $url): MailMessage => self::laisMail()
            ->subject('Konfirmasi email akun Lais')
            ->greeting("Halo, {$user->name}!")
            ->line('Satu langkah lagi: tekan tombol di bawah untuk memastikan email ini milikmu. Email ini dipakai kalau kamu lupa password.')
            ->action('Konfirmasi email', $url)
            ->line('Tautan ini berlaku '.config('auth.verification.expire', 60).' menit. Kalau kamu tidak mendaftar di Lais, abaikan saja email ini.'));

        ResetPassword::toMailUsing(fn (User $user, string $token): MailMessage => self::laisMail()
            ->subject('Atur ulang password Lais')
            ->greeting("Halo, {$user->name}!")
            ->line('Ada permintaan untuk mengatur ulang password akun Lais-mu.')
            ->action('Atur ulang password', url(route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], false)))
            ->line('Tautan ini berlaku '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' menit. Kalau kamu tidak memintanya, abaikan saja; password lamamu tetap berlaku.'));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // TLS ends at Coolify's proxy (trusted in bootstrap/app.php); links stay https.
        URL::forceHttps(app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Easy to remember for a shop owner, but never a password known to be leaked.
        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(8)->uncompromised()
            : null,
        );
    }
}
