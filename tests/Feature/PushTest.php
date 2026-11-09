<?php

namespace Tests\Feature;

use App\Jobs\SendPush;
use App\Models\Business;
use App\Models\Item;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Catalog;
use App\Services\WebPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * "Notifikasi di HP ini": web push for new shop orders, next to the bot.
 */
class PushTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.webpush.public_key' => 'BPublicKeyForTests', 'services.webpush.private_key' => 'private-key-for-tests', 'services.webpush.subject' => 'https://lais.test']);
        $this->owner = Business::factory()->create(['name' => 'Warung Ani'])->user;
    }

    public function test_an_owner_turns_notifications_on_and_off_for_this_browser(): void
    {
        $this->actingAs($this->owner)->get(route('whatsapp.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('pushKey', 'BPublicKeyForTests'));

        $this->post(route('push.store'), $this->subscription('a'))->assertRedirect();
        // The same browser again is one subscription, not two.
        $this->post(route('push.store'), $this->subscription('a'))->assertRedirect();
        $this->assertSame(1, $this->owner->pushSubscriptions()->count());

        $this->post(route('push.store'), [...$this->subscription('b'), 'endpoint' => 'http://push.example/b'])->assertSessionHasErrors('endpoint');
        $this->post(route('push.store'), [...$this->subscription('c'), 'keys' => ['p256dh' => '<script>', 'auth' => 'x']])->assertSessionHasErrors('keys.p256dh');

        // Someone else's browser is out of reach.
        $other = Business::factory()->create()->user;
        $this->actingAs($other)->delete(route('push.destroy'), ['endpoint' => 'https://push.example/a']);
        $this->assertSame(1, $this->owner->pushSubscriptions()->count());

        $this->actingAs($this->owner)->delete(route('push.destroy'), ['endpoint' => 'https://push.example/a'])->assertRedirect();
        $this->assertSame(0, $this->owner->pushSubscriptions()->count());
    }

    public function test_an_owner_keeps_the_ten_newest_browsers(): void
    {
        // Twelve browsers in one go is more than the route's own rate limit allows.
        $this->withoutMiddleware(ThrottleRequests::class)->actingAs($this->owner);

        foreach (range(1, 12) as $n) {
            $this->post(route('push.store'), $this->subscription("p{$n}"));
        }

        $this->assertSame(10, $this->owner->pushSubscriptions()->count());
        $this->assertFalse($this->owner->pushSubscriptions()->where('endpoint', 'https://push.example/p1')->exists());
    }

    public function test_a_new_shop_order_rings_every_browser_and_drops_the_ones_gone(): void
    {
        $business = $this->owner->currentBusiness();
        app(Catalog::class)->createMany($business, [['name' => 'Air Mineral', 'kind' => 'produk', 'unit' => 'botol', 'price' => 4000, 'opening_stock' => 24, 'opening_cost' => 2500]]);
        $water = Item::query()->where('name', 'Air Mineral')->sole();
        $this->actingAs($this->owner)->post(route('push.store'), $this->subscription('live'));
        $this->post(route('push.store'), $this->subscription('gone'));
        auth()->logout();

        $sent = [];
        $this->app->instance(WebPushSender::class, new class($sent) extends WebPushSender
        {
            /** @param  list<array{string, array<string, string>}>  $sent */
            public function __construct(public array &$sent) {}

            public function send(PushSubscription $subscription, array $message): bool
            {
                $this->sent[] = [$subscription->endpoint, $message];

                return ! str_ends_with($subscription->endpoint, '/gone');
            }
        });

        $this->post(route('shop.store', $business->slug), [
            'customer_name' => 'Bu Sari', 'customer_phone' => '081299990000', 'fulfillment' => 'ambil',
            'lines' => [['item_id' => $water->id, 'qty' => 5]],
        ])->assertSessionHasNoErrors();

        $this->assertSame([
            ['https://push.example/live', ['title' => 'Pesanan baru A001', 'body' => 'Bu Sari · 5 item · Rp20.000', 'url' => '/pesanan/A001']],
            ['https://push.example/gone', ['title' => 'Pesanan baru A001', 'body' => 'Bu Sari · 5 item · Rp20.000', 'url' => '/pesanan/A001']],
        ], $sent);
        $this->assertSame(['https://push.example/live'], $this->owner->pushSubscriptions()->pluck('endpoint')->all());
    }

    public function test_nothing_is_sent_or_offered_without_the_keys(): void
    {
        config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);
        PushSubscription::query()->create(['user_id' => $this->owner->id, 'endpoint_hash' => PushSubscription::hash('https://push.example/a'), 'endpoint' => 'https://push.example/a', 'public_key' => 'k', 'auth_token' => 'a']);
        Queue::fake();
        $business = $this->owner->currentBusiness();
        app(Catalog::class)->createMany($business, [['name' => 'Air Mineral', 'kind' => 'produk', 'unit' => 'botol', 'price' => 4000, 'opening_stock' => 24, 'opening_cost' => 2500]]);

        $this->post(route('shop.store', $business->slug), [
            'customer_name' => 'Bu Sari', 'customer_phone' => '081299990000', 'fulfillment' => 'ambil',
            'lines' => [['item_id' => Item::query()->sole()->id, 'qty' => 1]],
        ]);

        Queue::assertNotPushed(SendPush::class);
        $this->actingAs($this->owner)->get(route('whatsapp.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('pushKey', null));
    }

    /**
     * @return array{endpoint: string, keys: array{p256dh: string, auth: string}}
     */
    private function subscription(string $name): array
    {
        return ['endpoint' => "https://push.example/{$name}", 'keys' => ['p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM', 'auth' => 'tBHItJI5svbpez7KI4CCXg']];
    }
}
