<?php

namespace App\Console\Commands;

use App\Support\GeoIp\GeoIpDatabase;
use App\Support\Storage\DocumentStorage;
use GeoIp2\Database\Reader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PharData;
use Throwable;

/**
 * Fetches the MaxMind GeoLite2 City database and stores it durably.
 *
 * Run on demand rather than on a schedule or at boot. MaxMind publishes twice
 * a week; a container downloading 60MB on startup would make every deploy
 * slower and every scale-up hit their rate limit, and a request-time download
 * would be worse still.
 *
 * The file is written to the durable disk, which is what makes it survive a
 * deploy, and copied down locally so the reader can memory-map it.
 */
class DownloadGeoIpDatabase extends Command
{
    protected $signature = 'vaytoven:geoip-download
                            {--force : Download even when a database is already present}';

    protected $description = 'Download the MaxMind GeoLite2 City database and store it durably';

    public function handle(): int
    {
        $key = (string) config('services.maxmind.license_key');

        if ($key === '') {
            $this->error('MAXMIND_LICENSE_KEY is not set.');
            $this->line('Create a free key at maxmind.com → My Account → Manage License Keys,');
            $this->line('then set MAXMIND_LICENSE_KEY on the environment.');

            return self::FAILURE;
        }

        if (! DocumentStorage::isDurable()) {
            // Downloading onto a disk that loses it means doing this again on
            // every deploy, forever, without anyone noticing why geo keeps
            // reverting to country level.
            $this->error('Refusing to download: '.DocumentStorage::reason());

            return self::FAILURE;
        }

        $disk = Storage::disk(DocumentStorage::disk());

        if ($disk->exists(GeoIpDatabase::REMOTE_KEY) && ! $this->option('force')) {
            $this->info('A database is already stored. Use --force to replace it.');

            return self::SUCCESS;
        }

        $url = sprintf(
            'https://download.maxmind.com/app/geoip_download?edition_id=%s&license_key=%s&suffix=tar.gz',
            GeoIpDatabase::EDITION,
            urlencode($key),
        );

        $this->info('Downloading '.GeoIpDatabase::EDITION.'…');

        $archive = tempnam(sys_get_temp_dir(), 'geoip').'.tar.gz';

        try {
            $source = @fopen($url, 'rb');

            if (! $source) {
                // The usual cause is a rejected key, and MaxMind answers with
                // 401 rather than anything readable, so say so plainly.
                $this->error('Download failed. The most likely cause is a rejected licence key.');

                return self::FAILURE;
            }

            $target = fopen($archive, 'wb');
            $bytes  = stream_copy_to_stream($source, $target);
            fclose($source);
            fclose($target);

            $this->line('  '.number_format($bytes / 1048576, 1).' MB downloaded');

            $mmdb = $this->extract($archive);

            if (! $mmdb) {
                $this->error('The archive contained no .mmdb file.');

                return self::FAILURE;
            }

            // Opened before it is stored: a corrupt download that is accepted
            // here would replace a working database with a broken one, and the
            // failure would only show up as geo silently going missing.
            $reader = new Reader($mmdb);
            $built  = $reader->metadata()->buildEpoch;
            $reader->close();

            $this->line('  built '.date('Y-m-d', $built));

            $disk->put(GeoIpDatabase::REMOTE_KEY, fopen($mmdb, 'rb'));

            @mkdir(dirname(GeoIpDatabase::localPath()), 0775, true);
            @copy($mmdb, GeoIpDatabase::localPath());

            @unlink($mmdb);
            @unlink($archive);

            $this->info('Stored. Geo lookups will now resolve to city level.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            @unlink($archive);
            $this->error('Failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Pull the .mmdb out of MaxMind's tar.gz.
     *
     * The archive nests the database inside a dated directory, so the file is
     * found by extension rather than by an assumed path that changes weekly.
     */
    private function extract(string $archive): ?string
    {
        $target = sys_get_temp_dir().'/geoip-'.getmypid();
        @mkdir($target, 0775, true);

        $phar = new PharData($archive);
        $phar->decompress();

        $tar = str_replace('.tar.gz', '.tar', $archive);
        (new PharData($tar))->extractTo($target, null, true);
        @unlink($tar);

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($target)) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.mmdb')) {
                return $file->getPathname();
            }
        }

        return null;
    }
}
