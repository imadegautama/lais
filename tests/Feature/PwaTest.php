<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lais can be put on a phone's home screen (PWA): the manifest, its icons,
 * the service worker, and the page it shows offline are all served.
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_manifest_names_icons_that_exist_at_their_sizes(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['Lais', '/dashboard', 'standalone'], [$manifest['name'], $manifest['start_url'], $manifest['display']]);
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));

        foreach ($manifest['icons'] as $icon) {
            $size = getimagesize(public_path(ltrim($icon['src'], '/')));
            $this->assertNotFalse($size, $icon['src']);
            $this->assertSame($icon['sizes'], "{$size[0]}x{$size[1]}");
        }
    }

    public function test_the_page_points_at_the_manifest_and_the_offline_page_stands_alone(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false);

        $worker = (string) file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString("'/offline.html'", $worker);
        // Only whole page loads are answered from the cache; app data never is.
        $this->assertStringContainsString("request.mode !== 'navigate'", $worker);

        $offline = (string) file_get_contents(public_path('offline.html'));
        $this->assertStringContainsString('Kamu sedang offline', $offline);
        $this->assertDoesNotMatchRegularExpression('/(src|href)="https?:/', $offline, 'The offline page loads nothing from the network.');
    }
}
