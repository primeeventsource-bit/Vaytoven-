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
        // `health: '/up'` parameter. Cloud uses this as the default
        // readiness probe, so it must stay green on a freshly-booted instance.
        $response = $this->get('/up');

        $response->assertOk();
    }

    public function test_deeper_health_check_returns_structured_response(): void
    {
        // /health pings DB + Redis. Test environment has SQLite (DB ping
        // succeeds) but no Redis (ping fails), so we expect 503 with a
        // structured body. Either 200 or 503 is a *valid* response shape.
        $response = $this->get('/health');

        $this->assertContains($response->status(), [200, 503]);
        $response->assertJsonStructure([
            'status',
            'checks' => [
                'database' => ['ok'],
                'redis'    => ['ok'],
            ],
        ]);
    }
}
