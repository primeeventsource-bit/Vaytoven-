<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleTagTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_include_the_google_tag_once_in_the_head(): void
    {
        foreach (['/', '/properties', '/members', '/become-a-host', '/event-centers', '/login'] as $path) {
            $response = $this->get($path)->assertOk();
            $head = explode('</head>', $response->getContent())[0];
            $this->assertSame(1, substr_count($head, '/vyt-google-tag.js'), $path);
        }
    }

    public function test_google_ads_is_allowed_without_removing_existing_csp_protection(): void
    {
        $policy = $this->get('/')->headers->get('Content-Security-Policy');
        foreach (['script-src', 'img-src', 'connect-src', 'frame-src'] as $directive) {
            preg_match('/(?:^|; )'.preg_quote($directive, '/').' ([^;]+)/', $policy, $match);
            $this->assertStringContainsString('https://www.googletagmanager.com', $match[1]);
        }
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString('https://secure.nmi.com', $policy);
    }
}
