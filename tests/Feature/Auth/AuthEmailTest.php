<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * The emails Lais sends for an account: in Indonesian, with Lais's logo and
 * colors (resources/views/vendor/mail, AppServiceProvider::configureAuthEmails).
 */
class AuthEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_verification_email_speaks_indonesian_in_lais_colors(): void
    {
        $user = User::factory()->unverified()->create(['name' => 'Bu Rina']);
        $mail = (new VerifyEmail)->toMail($user);

        $this->assertSame('Konfirmasi email akun Lais', $mail->subject);
        $this->assertSame('Konfirmasi email', $mail->actionText);
        $this->assertStringContainsString('/email/verify/'.$user->id.'/', $mail->actionUrl);

        $html = (string) $mail->render();
        $this->assertStringContainsString('Halo, Bu Rina!', $html);
        // The logo rides inside the email, so it shows before the file is on any server.
        $this->assertStringContainsString('src="cid:lais-logo"', $html);
        $email = new Email;
        foreach ($mail->callbacks as $callback) {
            $callback($email);
        }
        $this->assertSame(['lais-logo'], array_map(fn ($part) => $part->getName(), $email->getAttachments()));
        // The theme is inlined, so the button carries the brand color itself.
        $this->assertMatchesRegularExpression('/class="button button-primary"[^>]*style="[^"]*#c25205/', $html);
        $this->assertStringContainsString('mohon tidak dibalas', $html);

        foreach (['Hello', 'Regards', 'All rights reserved', 'having trouble'] as $english) {
            $this->assertStringNotContainsString($english, $html);
        }
    }

    public function test_the_password_reset_email_links_to_the_reset_page(): void
    {
        $user = User::factory()->create(['email' => 'rina@contoh.id']);
        $mail = (new ResetPassword('token-uji'))->toMail($user);

        $this->assertSame('Atur ulang password Lais', $mail->subject);
        $this->assertSame(url(route('password.reset', ['token' => 'token-uji', 'email' => 'rina@contoh.id'], false)), $mail->actionUrl);
        $this->assertStringContainsString('berlaku 60 menit', (string) $mail->render());
    }
}
