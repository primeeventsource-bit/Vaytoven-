<?php

namespace Tests\Feature\Tracking;

use App\Models\LoginSession;
use App\Models\User;
use App\Services\GeoIp\GeoIpResult;
use App\Services\Tracking\AnomalyDetector;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnomalyDetectorTest extends TestCase
{
    use RefreshDatabase;

    private AnomalyDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = $this->app->make(AnomalyDetector::class);
    }

    private function priorLogin(User $user, array $attrs): LoginSession
    {
        return LoginSession::factory()->create(array_merge([
            'user_id' => $user->id,
            'auth_event' => 'login',
            'occurred_at' => now()->subDay(),
        ], $attrs));
    }

    public function test_first_ever_login_has_no_anomalies(): void
    {
        $user = User::factory()->create();
        $reasons = $this->detector->detect(
            $user,
            new GeoIpResult(country: 'US'),
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/121.0.0.0',
        );

        $this->assertSame([], $reasons);
    }

    public function test_returning_login_from_same_country_and_device_is_clean(): void
    {
        $user = User::factory()->create();
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/121.0.0.0';
        $this->priorLogin($user, [
            'country' => 'US',
            'user_agent' => $ua,
            'latitude' => 37.7749,
            'longitude' => -122.4194,
            'occurred_at' => now()->subDays(2),
        ]);

        $reasons = $this->detector->detect(
            $user,
            new GeoIpResult(country: 'US', latitude: 37.7749, longitude: -122.4194),
            $ua,
        );

        $this->assertSame([], $reasons);
    }

    public function test_new_country_is_flagged(): void
    {
        $user = User::factory()->create();
        $this->priorLogin($user, ['country' => 'US']);

        $reasons = $this->detector->detect(
            $user,
            new GeoIpResult(country: 'RU'),
            null,
        );

        $this->assertContains('new_country', $reasons);
    }

    public function test_new_device_is_flagged(): void
    {
        $user = User::factory()->create();
        $this->priorLogin($user, [
            'country' => 'US',
            'user_agent' => 'Mozilla/5.0 (Macintosh) Chrome/121.0.0.0',
        ]);

        $reasons = $this->detector->detect(
            $user,
            new GeoIpResult(country: 'US'),
            'Mozilla/5.0 (Linux; Android 14) Firefox/120.0',
        );

        $this->assertContains('new_device', $reasons);
    }

    public function test_browser_version_change_does_not_trip_new_device(): void
    {
        $user = User::factory()->create();
        $this->priorLogin($user, [
            'country' => 'US',
            'user_agent' => 'Mozilla/5.0 (Macintosh) Chrome/121.0.0.0',
        ]);

        $reasons = $this->detector->detect(
            $user,
            new GeoIpResult(country: 'US'),
            'Mozilla/5.0 (Macintosh) Chrome/122.5.1.4',  // upgraded browser
        );

        $this->assertNotContains('new_device', $reasons);
    }

    public function test_geo_impossibility_flagged_when_distance_too_far_in_too_short_a_time(): void
    {
        $user = User::factory()->create();
        // Last login: San Francisco 30 minutes ago.
        $this->priorLogin($user, [
            'country' => 'US',
            'latitude' => 37.7749,
            'longitude' => -122.4194,
            'user_agent' => 'common-ua',
            'occurred_at' => now()->subMinutes(30),
        ]);

        // New login: London — ~8600km away, 30min later.
        $reasons = $this->detector->detect(
            $user,
            new GeoIpResult(country: 'GB', latitude: 51.5074, longitude: -0.1278),
            'common-ua',
            CarbonImmutable::now(),
        );

        $this->assertContains('geo_impossibility', $reasons);
    }

    public function test_geo_impossibility_NOT_flagged_when_24h_passed(): void
    {
        $user = User::factory()->create();
        $this->priorLogin($user, [
            'country' => 'US',
            'latitude' => 37.7749,
            'longitude' => -122.4194,
            'user_agent' => 'ua',
            'occurred_at' => now()->subDay(),
        ]);

        $reasons = $this->detector->detect(
            $user,
            new GeoIpResult(country: 'GB', latitude: 51.5074, longitude: -0.1278),
            'ua',
            CarbonImmutable::now(),
        );

        $this->assertNotContains('geo_impossibility', $reasons);
    }

    public function test_known_tor_is_flagged_even_on_first_login(): void
    {
        $user = User::factory()->create();
        $reasons = $this->detector->detect(
            $user,
            new GeoIpResult(country: 'NL', is_tor: true),
            null,
        );

        $this->assertContains('known_tor', $reasons);
    }

    public function test_datacenter_is_flagged(): void
    {
        $user = User::factory()->create();
        $reasons = $this->detector->detect(
            $user,
            new GeoIpResult(country: 'US', is_datacenter: true),
            null,
        );

        $this->assertContains('datacenter', $reasons);
    }

    public function test_multiple_anomalies_compose(): void
    {
        $user = User::factory()->create();
        $this->priorLogin($user, ['country' => 'US', 'user_agent' => 'Chrome on Mac']);

        $reasons = $this->detector->detect(
            $user,
            new GeoIpResult(country: 'RU', is_tor: true, is_datacenter: true),
            'Firefox on Linux',
        );

        $this->assertContains('new_country', $reasons);
        $this->assertContains('new_device', $reasons);
        $this->assertContains('known_tor', $reasons);
        $this->assertContains('datacenter', $reasons);
    }
}
