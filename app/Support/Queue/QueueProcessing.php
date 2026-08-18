<?php

namespace App\Support\Queue;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Is queued work actually being carried out?
 *
 * A queue with no worker is the same failure mode as a mailer pointed at the
 * log: everything succeeds, nothing happens. `Mail::queue()` returns cleanly,
 * `Job::dispatch()` returns cleanly, and the rows pile up in `jobs` until
 * somebody notices that ops never got a single lead notification.
 *
 * The `main` environment sets QUEUE_CONNECTION=database and has no worker
 * process. `production` leaves it unset and so runs `sync`. Same code, two
 * completely different answers to "did that email send" — which is exactly the
 * kind of thing that has to be observable rather than remembered.
 *
 * Deliberately a heuristic. There is no way to ask a platform "is a worker
 * attached", so this asks the only question that has an honest answer: is
 * anything sitting in the queue that should have been picked up by now? A
 * backlog older than the threshold means nothing is draining it.
 */
class QueueProcessing
{
    /**
     * How long a job may wait before its presence means nobody is working.
     *
     * Generous on purpose: a busy worker can legitimately be a minute behind,
     * and a health check that cries wolf gets ignored, which costs more than
     * the delay it was reporting.
     */
    public const STALE_AFTER_SECONDS = 300;

    public static function driver(): string
    {
        $connection = Config::get('queue.default');

        return (string) Config::get("queue.connections.{$connection}.driver", $connection);
    }

    /**
     * True when dispatched work runs.
     *
     * `sync` runs inline, so it is always processed — slower requests, but
     * nothing is ever silently dropped. For a real queue the answer depends on
     * whether a worker is draining it.
     */
    public static function isProcessed(): bool
    {
        if (self::driver() === 'sync') {
            return true;
        }

        return self::backlogAgeInSeconds() < self::STALE_AFTER_SECONDS;
    }

    /**
     * Age of the oldest job that is ready to run, in seconds. Zero when the
     * queue is empty or cannot be inspected.
     *
     * Only jobs whose `available_at` has passed are counted: a delayed job
     * scheduled for next week is not evidence of a missing worker. Reserved
     * jobs are not counted either — those are in progress, which is a worker
     * doing its job.
     */
    public static function backlogAgeInSeconds(): int
    {
        if (self::driver() !== 'database' || ! Schema::hasTable('jobs')) {
            // Redis and SQS backlogs are not inspectable this cheaply. Claiming
            // a zero backlog is a lie, but a smaller one than claiming a
            // problem we have not measured.
            return 0;
        }

        try {
            $oldest = DB::table('jobs')
                ->whereNull('reserved_at')
                ->where('available_at', '<=', now()->getTimestamp())
                ->min('available_at');
        } catch (Throwable) {
            return 0;
        }

        if ($oldest === null) {
            return 0;
        }

        return max(0, now()->getTimestamp() - (int) $oldest);
    }

    public static function pendingCount(): int
    {
        if (self::driver() !== 'database' || ! Schema::hasTable('jobs')) {
            return 0;
        }

        try {
            return DB::table('jobs')->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /** Operator-facing explanation. Never shown to a visitor. */
    public static function reason(): ?string
    {
        if (self::isProcessed()) {
            return null;
        }

        return sprintf(
            '%d job(s) are waiting on the "%s" queue and the oldest has been ready for %d seconds, '
            .'so no worker is draining it. Queued mail and notifications are being accepted and never '
            .'delivered. Start a worker for this environment, or set QUEUE_CONNECTION=sync so the work '
            .'runs inline.',
            self::pendingCount(),
            self::driver(),
            self::backlogAgeInSeconds(),
        );
    }
}
