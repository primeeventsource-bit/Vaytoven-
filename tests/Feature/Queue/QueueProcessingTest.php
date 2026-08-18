<?php

namespace Tests\Feature\Queue;

use App\Support\Queue\QueueProcessing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A queue with no worker fails the same way a mailer pointed at the log does:
 * every dispatch succeeds and nothing happens.
 *
 * The live environments disagree about this — `main` sets
 * QUEUE_CONNECTION=database with no worker attached, `production` leaves it
 * unset and so runs `sync`. Same code, two different answers to "did that
 * email send", which is exactly the kind of thing that has to be observable.
 */
class QueueProcessingTest extends TestCase
{
    use RefreshDatabase;

    private function useDatabaseQueue(): void
    {
        config([
            'queue.default' => 'database',
            'queue.connections.database.driver' => 'database',
        ]);
    }

    private function queueJob(int $availableSecondsAgo, ?int $reservedAt = null): void
    {
        DB::table('jobs')->insert([
            'queue'        => 'default',
            'payload'      => '{}',
            'attempts'     => 0,
            'reserved_at'  => $reservedAt,
            'available_at' => now()->getTimestamp() - $availableSecondsAgo,
            'created_at'   => now()->getTimestamp() - $availableSecondsAgo,
        ]);
    }

    /** sync runs inline, so nothing can be silently dropped. */
    public function test_the_sync_driver_always_counts_as_processed(): void
    {
        config(['queue.default' => 'sync', 'queue.connections.sync.driver' => 'sync']);

        $this->assertTrue(QueueProcessing::isProcessed());
        $this->assertNull(QueueProcessing::reason());
    }

    public function test_an_empty_queue_counts_as_processed(): void
    {
        $this->useDatabaseQueue();

        $this->assertSame(0, QueueProcessing::pendingCount());
        $this->assertTrue(QueueProcessing::isProcessed());
    }

    /** A worker can legitimately be a few seconds behind. */
    public function test_a_job_queued_moments_ago_is_not_treated_as_stalled(): void
    {
        $this->useDatabaseQueue();
        $this->queueJob(availableSecondsAgo: 5);

        $this->assertTrue(QueueProcessing::isProcessed());
    }

    public function test_a_job_left_sitting_means_nothing_is_draining_the_queue(): void
    {
        $this->useDatabaseQueue();
        $this->queueJob(availableSecondsAgo: QueueProcessing::STALE_AFTER_SECONDS + 60);

        $this->assertFalse(QueueProcessing::isProcessed());
        $this->assertStringContainsString('no worker is draining it', QueueProcessing::reason());
        $this->assertSame(1, QueueProcessing::pendingCount());
    }

    /** A job scheduled for next week is not evidence of a missing worker. */
    public function test_a_delayed_job_is_not_mistaken_for_a_backlog(): void
    {
        $this->useDatabaseQueue();

        DB::table('jobs')->insert([
            'queue'        => 'default',
            'payload'      => '{}',
            'attempts'     => 0,
            'reserved_at'  => null,
            'available_at' => now()->addWeek()->getTimestamp(),
            'created_at'   => now()->getTimestamp(),
        ]);

        $this->assertTrue(QueueProcessing::isProcessed());
    }

    /** A reserved job is one a worker is currently running. */
    public function test_a_reserved_job_is_not_counted_as_a_backlog(): void
    {
        $this->useDatabaseQueue();
        $this->queueJob(
            availableSecondsAgo: QueueProcessing::STALE_AFTER_SECONDS + 60,
            reservedAt: now()->getTimestamp(),
        );

        $this->assertTrue(QueueProcessing::isProcessed());
    }

    // --- the health endpoint --------------------------------------------------
    //
    // The test environment has no Redis, so /health is 503 whatever the queue
    // is doing. These assert on the body, and on the status code being
    // UNCHANGED by the queue — which is the actual claim being made.

    public function test_the_health_endpoint_reports_the_queue(): void
    {
        config(['queue.default' => 'sync', 'queue.connections.sync.driver' => 'sync']);

        $this->getJson('/health')
            ->assertJsonPath('checks.queue.ok', true)
            ->assertJsonPath('checks.queue.driver', 'sync');
    }

    public function test_a_stalled_queue_is_reported(): void
    {
        $this->useDatabaseQueue();
        $this->queueJob(availableSecondsAgo: QueueProcessing::STALE_AFTER_SECONDS + 60);

        $this->getJson('/health')
            ->assertJsonPath('checks.queue.ok', false)
            ->assertJsonPath('checks.queue.pending', 1);
    }

    /**
     * Advisory, never critical. A stalled queue must not pull the container
     * out of rotation — serving the site is still better than not serving it.
     */
    public function test_a_stalled_queue_does_not_change_the_status_code(): void
    {
        $this->useDatabaseQueue();
        $healthy = $this->getJson('/health')->status();

        $this->queueJob(availableSecondsAgo: QueueProcessing::STALE_AFTER_SECONDS + 60);
        $stalled = $this->getJson('/health')->status();

        $this->assertSame($healthy, $stalled, 'the queue check must not affect readiness');
    }
}
