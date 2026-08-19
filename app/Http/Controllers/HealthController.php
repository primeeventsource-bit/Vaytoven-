<?php

namespace App\Http\Controllers;

use App\Support\Mail\MailDeliverability;
use App\Support\Storage\DocumentStorage;
use App\Support\Queue\QueueProcessing;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        // Splitting these matters. This endpoint is what the platform polls to
        // decide whether the container should receive traffic, so only things
        // that make the app unable to serve requests may influence the status
        // code. Mail is reported because it failed silently for months and
        // nobody could see it — but a mail outage must never take the site out
        // of rotation, which is what returning 503 here would do.
        $critical = [
            'database' => $this->checkDatabase(),
            'redis'    => $this->checkRedis(),
        ];

        $advisory = [
            'mail'    => $this->checkMail(),
            'queue'   => $this->checkQueue(),
            'storage' => $this->checkStorage(),
        ];

        $serving = collect($critical)->every(fn (array $check) => $check['ok'] === true);
        $degraded = collect($advisory)->contains(fn (array $check) => $check['ok'] !== true);

        return response()->json([
            'status' => match (true) {
                ! $serving => 'unhealthy',
                $degraded  => 'degraded',
                default    => 'ok',
            },
            'checks' => $critical + $advisory,
        ], $serving ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['ok' => true];
        } catch (Throwable $e) {
            // Don't leak driver-level details (DSN, credentials) in the response.
            // The exception class is enough to triage from logs.
            return ['ok' => false, 'error' => class_basename($e)];
        }
    }

    /**
     * Mail was misconfigured in production for months without anything
     * noticing, because a broken mailer produces no errors — it produces
     * silence. Nothing was watching for silence, so this endpoint watches now.
     *
     * Reports configuration, not connectivity: opening an SMTP session on every
     * health poll would be its own outage. The reason string names a transport
     * and never a credential.
     */
    private function checkMail(): array
    {
        $transport = config('mail.default');

        if (! MailDeliverability::isDeliverable()) {
            return ['ok' => false, 'transport' => $transport, 'error' => MailDeliverability::reason()];
        }

        // Configured is not delivering. Reporting "ok" purely on configuration
        // is how this endpoint came to show mail as healthy while every send
        // was being refused with SMTP 535 — a host, a username and a password
        // were all present and the password was simply wrong. A false green is
        // worse than a known-bad state, because nobody investigates green.
        if (! MailDeliverability::isVerified()) {
            return [
                'ok'        => false,
                'transport' => $transport,
                'verified'  => false,
                'error'     => MailDeliverability::unverifiedReason(),
            ];
        }

        return [
            'ok'          => true,
            'transport'   => $transport,
            'verified'    => true,
            'last_sent_at' => MailDeliverability::lastSuccessfulSendAt(),
        ];
    }

    /**
     * A queue with no worker fails exactly like a mailer pointed at the log:
     * every dispatch succeeds and nothing happens. Advisory rather than
     * critical — a stalled queue must not pull the container out of rotation,
     * because serving the site is still better than not serving it.
     */
    private function checkQueue(): array
    {
        if (QueueProcessing::isProcessed()) {
            return ['ok' => true, 'driver' => QueueProcessing::driver()];
        }

        return [
            'ok'      => false,
            'driver'  => QueueProcessing::driver(),
            'pending' => QueueProcessing::pendingCount(),
            'error'   => QueueProcessing::reason(),
        ];
    }

    /**
     * Can a photo actually be written to the bucket?
     *
     * DocumentStorage::isDurable() answers a different question — it reads the
     * driver name and says whether a file written there WOULD survive a deploy.
     * It never touches the bucket. So a listing photo upload is offered to
     * staff on the strength of the string "s3", and if the bucket is
     * unreachable the request hangs until it times out with the image lost and
     * nothing written anywhere. That is the same shape of failure as the mailer
     * that was configured, green, and refusing every send.
     *
     * This one makes the round trip: write a few bytes, read them back, delete
     * them. Advisory, never critical — a bucket outage must not pull the
     * container out of rotation, because a site that serves listings without
     * accepting new photos is far better than no site.
     *
     * The probe key is fixed rather than unique so repeated polls cannot litter
     * the bucket if the delete is what is failing.
     */
    private function checkStorage(): array
    {
        $disk = DocumentStorage::disk();

        if (! DocumentStorage::isDurable()) {
            return ['ok' => false, 'disk' => $disk, 'error' => DocumentStorage::reason()];
        }

        $key      = '_health/probe.txt';
        $expected = 'vaytoven-health';
        $started  = microtime(true);

        try {
            Storage::disk($disk)->put($key, $expected);
            $readBack = Storage::disk($disk)->get($key);
            Storage::disk($disk)->delete($key);
        } catch (Throwable $e) {
            return [
                'ok'     => false,
                'disk'   => $disk,
                'driver' => DocumentStorage::driver(),
                // Class name only: the message can carry the endpoint and
                // signed query parameters, and this endpoint is public.
                'error'  => 'write failed: '.class_basename($e),
            ];
        }

        return [
            'ok'          => $readBack === $expected,
            'disk'        => $disk,
            'driver'      => DocumentStorage::driver(),
            'round_trip_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    private function checkRedis(): array
    {
        try {
            $pong = Redis::connection()->ping();

            // phpredis returns "+PONG" or true depending on version; predis
            // returns "PONG". Treat any truthy response as healthy.
            return ['ok' => (bool) $pong];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => class_basename($e)];
        }
    }
}
