<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_the_policies_google_links_to_are_public(): void
    {
        $this->get('/privasi')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('legal/privacy'));
        $this->get('/syarat')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('legal/terms'));
    }
}
