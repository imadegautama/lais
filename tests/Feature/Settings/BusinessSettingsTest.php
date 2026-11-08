<?php

namespace Tests\Feature\Settings;

use App\Enums\BusinessCategory;
use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BusinessSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_shows_the_business_and_its_shop_link(): void
    {
        $business = Business::factory()->create(['name' => 'Warung Ani', 'slug' => 'warung-ani', 'category' => BusinessCategory::Kuliner]);

        $this->actingAs($business->user)
            ->get(route('business.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/business')
                ->where('business.name', 'Warung Ani')
                ->where('business.category', 'kuliner')
                ->where('business.shopUrl', route('shop.show', 'warung-ani'))
                ->where('business.open', true),
            );
    }

    public function test_settings_opens_on_the_business_tab(): void
    {
        $business = Business::factory()->create();

        $this->actingAs($business->user)->get(route('settings'))->assertRedirect('/settings/business');
    }

    public function test_renaming_keeps_the_shop_link_so_shared_links_still_work(): void
    {
        $business = Business::factory()->create(['name' => 'Warung Ani', 'slug' => 'warung-ani']);

        $this->actingAs($business->user)
            ->patch(route('business.update'), ['name' => 'Dapur Ani', 'category' => 'kelontong'])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'Data usaha disimpan.');

        $business->refresh();
        $this->assertSame(['Dapur Ani', BusinessCategory::Kelontong, 'warung-ani'], [$business->name, $business->category, $business->slug]);
    }

    public function test_a_name_is_required_and_the_kind_must_be_on_the_list(): void
    {
        $business = Business::factory()->create(['name' => 'Warung Ani']);

        $this->actingAs($business->user)
            ->patch(route('business.update'), ['name' => '', 'category' => 'bengkel'])
            ->assertSessionHasErrors(['name' => 'Isi nama usaha.', 'category' => 'Pilih jenis usaha dari daftar.']);

        $this->assertSame('Warung Ani', $business->refresh()->name);
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get(route('business.edit'))->assertRedirect(route('login'));
    }
}
