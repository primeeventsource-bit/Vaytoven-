<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis'    => $this->checkRedis(),
        ];

        $allHealthy = collect($checks)->every(fn (array $check) => $check['ok'] === true);

        return response()->json([
            'status' => $allHealthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $allHealthy ? 200 : 503);
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
