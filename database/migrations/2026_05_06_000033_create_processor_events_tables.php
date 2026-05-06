<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-processor event tables for webhook idempotency (FR-4.3 / NFR-1).
     *
     * Each processor has its own table with `event_id` UNIQUE — replayed
     * webhooks become no-ops because the INSERT fails. The webhook handler
     * MUST insert before processing; if the insert fails with a unique
     * violation, the handler short-circuits.
     */
    public function up(): void
    {
        $processors = [
            'stripe', 'authorizenet', 'nmi', 'nuvei', 'mes',
            'paymentcloud', 'ems', 'nexio', 'netevia', 'kurv',
        ];

        foreach ($processors as $proc) {
            Schema::create("{$proc}_events", function (Blueprint $table) {
                $table->id();
                $table->string('event_id', 128)->unique();
                $table->string('event_type', 128);
                $table->json('payload')->nullable();
                $table->timestamp('processed_at');
                $table->timestamps();

                $table->index('event_type');
            });
        }
    }

    public function down(): void
    {
        $processors = [
            'stripe', 'authorizenet', 'nmi', 'nuvei', 'mes',
            'paymentcloud', 'ems', 'nexio', 'netevia', 'kurv',
        ];

        foreach ($processors as $proc) {
            Schema::dropIfExists("{$proc}_events");
        }
    }
};
