<?php

namespace Tests\Feature\GeoIp;

use App\Support\GeoIp\GeoIpDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The MaxMind database has to survive a deploy.
 *
 * The reader memory-maps a real file, and the container filesystem is wiped on
 * every deploy — so the database lives on the durable disk and is copied down
 * on first use. If any of that fails the answer must be "no database", which
 * sends the resolver back to Cloudflare headers rather than breaking lookups.
 */
class GeoIpDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['services.maxmind.mmdb_path' => null]);

        // Each test starts without a local copy.
        @unlink(GeoIpDatabase::localPath());
    }

    protected function tearDown(): void
    {
        @unlink(GeoIpDatabase::localPath());

        parent::tearDown();
    }

    public function test_an_explicit_path_wins_over_object_storage(): void
    {
        config(['services.maxmind.mmdb_path' => '/opt/geoip/GeoLite2-City.mmdb']);

        $this->assertSame('/opt/geoip/GeoLite2-City.mmdb', GeoIpDatabase::localPath());
    }

    public function test_it_defaults_to_a_path_under_storage(): void
    {
        $this->assertStringContainsString('GeoLite2-City.mmdb', GeoIpDatabase::localPath());
    }

    /** Nothing stored means no database, not an exception. */
    public function test_it_reports_nothing_when_the_database_has_never_been_downloaded(): void
    {
        $this->assertNull(GeoIpDatabase::ensureAvailable());
        $this->assertFalse(GeoIpDatabase::existsLocally());
        $this->assertNull(GeoIpDatabase::builtAt());
    }

    public function test_it_copies_the_database_down_from_durable_storage(): void
    {
        Storage::disk('local')->put(GeoIpDatabase::REMOTE_KEY, 'pretend-mmdb-bytes');

        $path = GeoIpDatabase::ensureAvailable();

        $this->assertNotNull($path);
        $this->assertTrue(GeoIpDatabase::existsLocally());
        $this->assertSame('pretend-mmdb-bytes', file_get_contents($path));
    }

    /** The 60MB copy happens once per container, not per lookup. */
    public function test_a_second_call_uses_the_local_copy(): void
    {
        Storage::disk('local')->put(GeoIpDatabase::REMOTE_KEY, 'original-bytes');

        GeoIpDatabase::ensureAvailable();

        // Change what is stored remotely; the local copy must be preferred.
        Storage::disk('local')->put(GeoIpDatabase::REMOTE_KEY, 'replaced-bytes');

        $this->assertSame('original-bytes', file_get_contents(GeoIpDatabase::ensureAvailable()));
    }

    /** A half-written file must never be readable as a database. */
    public function test_no_partial_file_is_left_behind_under_its_real_name(): void
    {
        Storage::disk('local')->put(GeoIpDatabase::REMOTE_KEY, str_repeat('x', 4096));

        GeoIpDatabase::ensureAvailable();

        $leftovers = glob(dirname(GeoIpDatabase::localPath()).'/*.part');

        $this->assertEmpty($leftovers, 'a .part file survived the copy');
    }

    // --- the command --------------------------------------------------------------

    public function test_the_download_refuses_without_a_licence_key(): void
    {
        config(['services.maxmind.license_key' => null]);

        $this->artisan('vaytoven:geoip-download')
            ->expectsOutputToContain('MAXMIND_LICENSE_KEY is not set')
            ->assertFailed();
    }

    /**
     * Downloading onto a disk that loses it means doing this again on every
     * deploy, forever, without anyone noticing why geo keeps reverting to
     * country level.
     */
    public function test_the_download_refuses_when_storage_is_not_durable(): void
    {
        config([
            'services.maxmind.license_key' => 'a-key',
            'filesystems.default' => 'local',
            'filesystems.disks.local.driver' => 'local',
        ]);
        app()->detectEnvironment(fn () => 'production');

        $this->artisan('vaytoven:geoip-download')
            ->expectsOutputToContain('Refusing to download')
            ->assertFailed();
    }

    public function test_it_does_not_replace_an_existing_database_without_force(): void
    {
        config(['services.maxmind.license_key' => 'a-key']);
        Storage::disk('local')->put(GeoIpDatabase::REMOTE_KEY, 'existing');

        $this->artisan('vaytoven:geoip-download')
            ->expectsOutputToContain('already stored')
            ->assertSuccessful();

        $this->assertSame('existing', Storage::disk('local')->get(GeoIpDatabase::REMOTE_KEY));
    }
}
