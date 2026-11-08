<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TimeZoneTest extends TestCase
{
    public function test_pages_receive_the_zone_set_by_app_timezone(): void
    {
        $this->get(route('login'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('timeZone.name', 'Asia/Jakarta')
            ->where('timeZone.label', 'WIB'));

        config(['app.timezone' => 'Asia/Makassar']);

        $this->get(route('login'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('timeZone.name', 'Asia/Makassar')
            ->where('timeZone.label', 'WITA'));
    }
}
