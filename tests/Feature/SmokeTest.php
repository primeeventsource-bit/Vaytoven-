<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Vaytoven', escape: false);
    }

    public function test_health_endpoint_responds(): void
    {
        // Laravel 11 ships the /up health route via bootstrap/app.php's
        // `health: '/up'` parameter. ACA will use this as the readiness
        // probe, so it must stay green even on a freshly-booted instance.
        $response = $this->get('/up');

        $response->assertOk();
    }
}
