<?php

namespace App\Http\Controllers;

use App\Support\Mail\MailDeliverability;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
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
            'mail' => $this->checkMail(),
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
        if (MailDeliverability::isDeliverable()) {
            return ['ok' => true, 'transport' => config('mail.default')];
        }

        return [
            'ok'        => false,
            'transport' => config('mail.default'),
            'error'     => MailDeliverability::reason(),
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
