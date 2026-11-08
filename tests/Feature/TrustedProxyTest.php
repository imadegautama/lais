<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_links_follow_the_https_scheme_forwarded_by_the_reverse_proxy(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.5'])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'lais.example.com',
            ])
            ->get('/dashboard');

        $response->assertRedirect();
        $this->assertStringStartsWith('https://lais.example.com/', $response->headers->get('Location'));
    }
}
