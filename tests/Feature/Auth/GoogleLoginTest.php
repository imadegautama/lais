<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use RuntimeException;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => 'lais-test.apps.googleusercontent.com', 'services.google.client_secret' => 'secret']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function google(array $attributes = []): GoogleUser
    {
        return GoogleUser::fake([
            'id' => 'google-123',
            'sub' => 'google-123',
            'name' => 'Rina Wati',
            'email' => 'Rina@Gmail.com',
            'email_verified' => true,
            ...$attributes,
        ]);
    }

    public function test_the_button_sends_the_owner_to_google(): void
    {
        $response = $this->get(route('google.redirect'));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://accounts.google.com/', $response->headers->get('Location'));
        $this->assertStringContainsString('client_id=lais-test.apps.googleusercontent.com', $response->headers->get('Location'));
    }

    public function test_a_new_owner_gets_a_verified_account_and_opens_the_books(): void
    {
        Socialite::fake('google', $this->google());

        $this->get(route('google.callback'))->assertRedirect(route('onboarding.show'));

        $user = User::query()->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('rina@gmail.com', $user->email);
        $this->assertSame('Rina Wati', $user->name);
        $this->assertSame('google-123', $user->google_id);
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_an_existing_email_account_is_joined_not_duplicated(): void
    {
        $owner = User::factory()->unverified()->create(['email' => 'rina@gmail.com']);
        Socialite::fake('google', $this->google());

        $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

        $this->assertSame(1, User::query()->count());
        $this->assertAuthenticatedAs($owner);
        $this->assertSame('google-123', $owner->refresh()->google_id);
        $this->assertTrue($owner->hasVerifiedEmail());
    }

    public function test_the_google_id_finds_the_account_even_after_the_email_changed(): void
    {
        $owner = User::factory()->create(['email' => 'warung@example.com']);
        $owner->forceFill(['google_id' => 'google-123'])->save();
        Socialite::fake('google', $this->google());

        $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

        $this->assertSame(1, User::query()->count());
        $this->assertAuthenticatedAs($owner);
    }

    public function test_an_unverified_google_email_is_never_joined_to_an_account(): void
    {
        User::factory()->create(['email' => 'rina@gmail.com']);
        Socialite::fake('google', $this->google(['email_verified' => false]));

        $this->get(route('google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertNull(User::query()->sole()->google_id);
    }

    public function test_an_expired_session_or_a_failed_call_sends_the_owner_back(): void
    {
        Socialite::fake('google', fn () => throw new InvalidStateException);
        $this->get(route('google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('google');

        Exceptions::fake();
        Socialite::fake('google', fn () => throw new RuntimeException('token endpoint down'));
        $this->get(route('google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('google');

        Exceptions::assertReported(RuntimeException::class);
        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_two_factor_still_asks_for_the_code(): void
    {
        $owner = User::factory()->withTwoFactor()->create(['email' => 'rina@gmail.com']);
        Socialite::fake('google', $this->google());

        $this->get(route('google.callback'))->assertRedirect(route('two-factor.login'))->assertSessionHas('login.id', $owner->id);

        $this->assertGuest();
    }

    public function test_a_signed_in_owner_is_not_sent_to_google_again(): void
    {
        $this->actingAs(User::factory()->create())->get(route('google.redirect'))->assertRedirect(route('dashboard'));
    }

    public function test_the_button_shows_only_when_google_is_configured(): void
    {
        $this->get(route('login'))->assertInertia(fn (AssertableInertia $page) => $page->where('googleLogin', true));
        $this->get(route('register'))->assertInertia(fn (AssertableInertia $page) => $page->where('googleLogin', true));

        config(['services.google.client_id' => null]);

        $this->get(route('login'))->assertInertia(fn (AssertableInertia $page) => $page->where('googleLogin', false));
    }

    public function test_the_google_id_never_reaches_the_browser(): void
    {
        Socialite::fake('google', $this->google());
        $this->get(route('google.callback'));

        $this->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page->missing('auth.user.google_id'));
    }
}
