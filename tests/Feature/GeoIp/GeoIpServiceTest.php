<?php

namespace Tests\Feature\GeoIp;

use App\Models\TrackingEvent;
use App\Services\GeoIp\CachedGeoIpService;
use App\Services\GeoIp\GeoIpResult;
use App\Services\GeoIp\GeoIpService;
use App\Services\GeoIp\NoOpGeoIpService;
use App\Services\Tracking\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GeoIpServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_default_binding_resolves_to_a_geoip_service(): void
    {
        $resolved = $this->app->make(GeoIpService::class);
        $this->assertInstanceOf(GeoIpService::class, $resolved);
    }

    public function test_noop_service_returns_empty_result_for_any_ip(): void
    {
        $svc = new NoOpGeoIpService();

        $result = $svc->lookup('8.8.8.8');

        $this->assertInstanceOf(GeoIpResult::class, $result);
        $this->assertFalse($result->isResolved());
        $this->assertNull($result->country);
        $this->assertFalse($result->is_vpn);
    }

    public function test_noop_handles_null_ip(): void
    {
        $this->assertFalse((new NoOpGeoIpService())->lookup(null)->isResolved());
    }

    public function test_cached_service_only_calls_upstream_once_per_ip(): void
    {
        $upstream = Mockery::mock(GeoIpService::class);
        $upstream->shouldReceive('lookup')
            ->once()  // critical assertion: second call hits cache, not upstream
            ->with('1.2.3.4')
            ->andReturn(new GeoIpResult(country: 'US', city: 'Mountain View'));

        $cache = Cache::store('array');
        $cached = new CachedGeoIpService($upstream, $cache);

        $first = $cached->lookup('1.2.3.4');
        $second = $cached->lookup('1.2.3.4');

        $this->assertSame('US', $first->country);
        $this->assertSame('US', $second->country);
        $this->assertSame('Mountain View', $second->city);
    }

    public function test_cached_service_passes_through_distinct_ips(): void
    {
        $upstream = Mockery::mock(GeoIpService::class);
        $upstream->shouldReceive('lookup')->with('1.1.1.1')
            ->once()
            ->andReturn(new GeoIpResult(country: 'US'));
        $upstream->shouldReceive('lookup')->with('2.2.2.2')
            ->once()
            ->andReturn(new GeoIpResult(country: 'CA'));

        $cached = new CachedGeoIpService($upstream, Cache::store('array'));

        $this->assertSame('US', $cached->lookup('1.1.1.1')->country);
        $this->assertSame('CA', $cached->lookup('2.2.2.2')->country);
    }

    public function test_cached_service_negative_caches_unresolved_results(): void
    {
        // First call: upstream throws → CachedGeoIpService should NOT bubble.
        // Actually our contract is upstream MUST NOT throw, but if it does,
        // the cached layer can't help. We test the negative-cache happy path:
        // upstream returns empty result, we cache it with a short TTL.
        $upstream = Mockery::mock(GeoIpService::class);
        $upstream->shouldReceive('lookup')
            ->once()  // empty result is still cached, second call shouldn't hit upstream
            ->with('private-ip')
            ->andReturn(GeoIpResult::empty());

        $cached = new CachedGeoIpService($upstream, Cache::store('array'));

        $this->assertFalse($cached->lookup('private-ip')->isResolved());
        $this->assertFalse($cached->lookup('private-ip')->isResolved());
    }

    public function test_tracking_service_attaches_geo_to_metadata_when_resolved(): void
    {
        // Replace the bound GeoIpService with one that returns a known result.
        $this->app->bind(GeoIpService::class, function () {
            return new class implements GeoIpService {
                public function lookup(?string $ipAddress): GeoIpResult
                {
                    return new GeoIpResult(
                        country: 'US',
                        region: 'California',
                        city: 'San Francisco',
                        latitude: 37.7749,
                        longitude: -122.4194,
                    );
                }
            };
        });

        $svc = $this->app->make(TrackingService::class);
        $event = $svc->record(
            eventType: 'page_view',
            ipAddress: '1.2.3.4',
            metadata: ['path' => '/'],
        );

        $this->assertArrayHasKey('geo', $event->metadata);
        $this->assertSame('US', $event->metadata['geo']['country']);
        $this->assertSame('San Francisco', $event->metadata['geo']['city']);
        $this->assertSame('/', $event->metadata['path']);
    }

    public function test_tracking_service_omits_geo_metadata_when_unresolved(): void
    {
        // Default binding is NoOp in test env (no MAXMIND_MMDB_PATH). Confirm
        // that a fully-empty GeoIp result doesn't add a 'geo' key at all.
        $svc = $this->app->make(TrackingService::class);

        $event = $svc->record(
            eventType: 'page_view',
            ipAddress: '127.0.0.1',
            metadata: ['path' => '/'],
        );

        $this->assertArrayNotHasKey('geo', $event->metadata);
    }

    public function test_tracking_service_handles_null_ip_without_geo_lookup(): void
    {
        $upstream = Mockery::mock(GeoIpService::class);
        $upstream->shouldNotReceive('lookup');  // null IP must skip upstream

        $this->app->instance(GeoIpService::class, $upstream);

        $svc = $this->app->make(TrackingService::class);
        $svc->record(eventType: 'page_view', ipAddress: null);

        $this->assertSame(1, TrackingEvent::count());
    }
}
