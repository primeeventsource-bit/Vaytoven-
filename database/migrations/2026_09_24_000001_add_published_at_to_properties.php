<?php

use App\Enums\ActivityType;
use App\Enums\PropertyStatus;
use App\Models\Property;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a listing first went live.
 *
 * Stays orders newest advertisement first, and nothing recorded that moment:
 * created_at is when staff started building the listing, updated_at moves
 * every time anyone fixes a typo, and the member's account date has nothing
 * to do with it.
 *
 * Set once, by the Property observer, the first time a listing becomes
 * active. A later edit — or a pause and re-activation — never moves it, so a
 * listing cannot be bumped back to the top of Stays by editing it.
 *
 * The backfill reads the records that already exist, oldest first:
 *   1. the activation on the activity log (append-only)
 *   2. the admin audit log, for listings created straight into active
 *   3. created_at, for live listings that predate both
 * Drafts and paused listings that never went live stay null.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('properties', 'published_at')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->timestamp('published_at')->nullable()->index();
            });
        }

        $activations = DB::table('tracking_events')
            ->where('event_type', ActivityType::AdvertisementActivated->value)
            ->whereNotNull('subject_reference')
            ->selectRaw('subject_reference, MIN(occurred_at) as first_at')
            ->groupBy('subject_reference')
            ->pluck('first_at', 'subject_reference');

        $audited = DB::table('admin_audit_logs')
            ->where('subject_type', Property::class)
            ->whereIn('action', ['property.create', 'property.status_changed'])
            ->orderBy('occurred_at')
            ->get(['subject_id', 'action', 'payload', 'occurred_at'])
            ->filter(function ($row) {
                $payload = json_decode((string) $row->payload, true) ?: [];

                return ($payload['status'] ?? $payload['to'] ?? null) === PropertyStatus::Active->value;
            })
            ->groupBy('subject_id')
            ->map(fn ($rows) => $rows->first()->occurred_at);

        DB::table('properties')
            ->whereNull('published_at')
            ->orderBy('id')
            ->select('id', 'reference', 'status', 'created_at')
            ->chunkById(200, function ($rows) use ($activations, $audited) {
                foreach ($rows as $row) {
                    $at = $activations[$row->reference] ?? $audited[$row->id] ?? null;

                    if (! $at && $row->status === PropertyStatus::Active->value) {
                        $at = $row->created_at;
                    }

                    if ($at) {
                        DB::table('properties')->where('id', $row->id)->update(['published_at' => $at]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['published_at']);
            $table->dropColumn('published_at');
        });
    }
};
