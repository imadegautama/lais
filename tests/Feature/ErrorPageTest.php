<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);
    }

    public function test_a_missing_page_is_our_own_page_and_keeps_its_status(): void
    {
        $this->get('/halaman-yang-tidak-ada')->assertNotFound()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('errors/show')->where('status', 404));
    }

    public function test_a_crash_shows_no_detail_of_what_went_wrong(): void
    {
        Route::get('/uji-rusak', fn () => throw new RuntimeException('rahasia internal'));

        $response = $this->get('/uji-rusak')->assertInternalServerError()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('errors/show')->where('status', 500));

        $this->assertStringNotContainsString('rahasia internal', (string) $response->getContent());
    }

    public function test_another_business_record_stays_a_plain_404(): void
    {
        $contact = Business::factory()->create()->contacts()->create(['type' => 'pelanggan', 'name' => 'Rahasia']);

        $this->actingAs(Business::factory()->create()->user)->get(route('contacts.show', $contact))->assertNotFound()
            ->assertDontSee('Rahasia');
    }

    public function test_callers_that_read_json_keep_json(): void
    {
        $this->getJson('/halaman-yang-tidak-ada')->assertNotFound()->assertJsonStructure(['message']);
    }
}
