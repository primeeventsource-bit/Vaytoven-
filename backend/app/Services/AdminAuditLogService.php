<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Centralised admin audit logging.
 *
 * Every privileged write performed by an admin should call ::log() so we have a
 * permanent before/after record (FR-7.4 + FR-9.7). Reads are NOT logged here —
 * use a separate read-access trail if needed for sensitive data.
 *
 * Usage:
 *   AdminAuditLogService::log(
 *       admin:  $request->user(),
 *       action: 'members_enquiry.qualified',
 *       target: $enquiry,
 *       before: $originalAttributes,    // pre-update snapshot
 *       after:  $enquiry->fresh()->getAttributes(),
 *       request: $request,              // optional, for IP + UA
 *       reason: $request->input('reason'), // optional
 *   );
 */
class AdminAuditLogService
{
    /**
     * Catalog of known action strings. Not enforced at the column level — string
     * column gives us flexibility — but adding new action strings here gives a
     * single source of truth for grep-ability and admin UI labels.
     */
    public const ACTIONS = [
        // User
        'user.suspended'          => 'Suspended user',
        'user.unbanned'           => 'Unbanned user',
        'user.role_granted'       => 'Granted role',
        'user.role_revoked'       => 'Revoked role',
        'user.impersonation.start'=> 'Started impersonation',
        'user.impersonation.end'  => 'Ended impersonation',

        // Property
        'property.approved'       => 'Approved listing',
        'property.rejected'       => 'Rejected listing',
        'property.delisted'       => 'Delisted listing',

        // Booking
        'booking.refunded'        => 'Refunded booking',
        'booking.force_cancelled' => 'Force-cancelled booking',

        // Dispute
        'dispute.resolved'        => 'Resolved dispute',
        'payout.released'         => 'Released payout',

        // Members enquiry
        'members_enquiry.assigned'   => 'Assigned enquiry',
        'members_enquiry.contacted'  => 'Marked contacted',
        'members_enquiry.qualified'  => 'Qualified enquiry',
        'members_enquiry.onboarded'  => 'Onboarded as listing',
        'members_enquiry.rejected'   => 'Rejected enquiry',
        'members_enquiry.unflagged'  => 'Cleared spam flag',
        'members_enquiry.exported'   => 'Exported enquiries',
    ];

    /**
     * Fields that should never appear in audit diffs — they are pure noise
     * (timestamps written automatically) or sensitive (raw secrets).
     */
    private const STRIPPED_KEYS = [
        'updated_at', 'created_at', 'deleted_at',
        'remember_token', 'password_hash', 'two_factor_secret',
    ];

    /**
     * Persist an audit log entry. Returns the stored AdminAuditLog row.
     *
     * @param  User                   $admin    The acting admin (must have admin role).
     * @param  string                 $action   One of self::ACTIONS keys, or a free-form string.
     * @param  Model|null             $target   The model being acted upon. Inferred type/id when given.
     * @param  array<string,mixed>|null $before Pre-change snapshot (Model::getOriginal() or ::getAttributes() before update).
     * @param  array<string,mixed>|null $after  Post-change snapshot (Model::fresh()->getAttributes()).
     * @param  Request|null           $request HTTP request for IP / user agent capture.
     * @param  string|null            $reason  Free-form admin-supplied reason.
     */
    public static function log(
        User $admin,
        string $action,
        ?Model $target = null,
        ?array $before = null,
        ?array $after  = null,
        ?Request $request = null,
        ?string $reason = null,
    ): AdminAuditLog {

        [$type, $id, $uuid] = self::resolveTarget($target);

        $log = AdminAuditLog::create([
            'admin_user_id' => $admin->id,
            'action'        => $action,
            'target_type'   => $type,
            'target_id'     => $id,
            'target_uuid'   => $uuid,
            'before_state'  => self::sanitise($before),
            'after_state'   => self::sanitise($after),
            'reason'        => $reason,
            'ip_address'    => $request?->ip(),
            'user_agent'    => $request?->userAgent(),
            'created_at'    => now(),
        ]);

        // Mirror to the application log for tooling that aggregates from there.
        Log::info('admin.audit', [
            'log_id'      => $log->id,
            'actor_id'    => $admin->id,
            'action'      => $action,
            'target'      => $type ? "$type:" . ($uuid ?? $id) : null,
            'has_diff'    => $before !== null || $after !== null,
        ]);

        return $log;
    }

    /**
     * Convenience wrapper: log a model update by snapshotting before + after automatically.
     *
     *   AdminAuditLogService::logUpdate($admin, 'members_enquiry.qualified', $enquiry, $request, function () use ($enquiry) {
     *       $enquiry->markQualified();
     *   });
     */
    public static function logUpdate(
        User $admin,
        string $action,
        Model $target,
        Request $request,
        callable $mutator,
        ?string $reason = null,
    ): AdminAuditLog {
        $before = $target->getOriginal();
        $mutator();
        $target->refresh();
        $after = $target->getAttributes();

        return self::log(
            admin:   $admin,
            action:  $action,
            target:  $target,
            before:  $before,
            after:   $after,
            request: $request,
            reason:  $reason,
        );
    }

    private static function resolveTarget(?Model $target): array
    {
        if (! $target) return [null, null, null];

        $type = $target->getTable();
        $key  = $target->getKey();

        if (is_int($key) || (is_string($key) && ctype_digit($key))) {
            return [$type, (int) $key, null];
        }
        if (is_string($key)) {
            return [$type, null, $key]; // assume uuid
        }
        return [$type, null, null];
    }

    private static function sanitise(?array $state): ?array
    {
        if (! $state) return null;

        $clean = [];
        foreach ($state as $k => $v) {
            if (in_array($k, self::STRIPPED_KEYS, true)) continue;
            // Recursively sanitise nested arrays (e.g. casts to JSON columns)
            if (is_array($v)) {
                $clean[$k] = self::sanitise($v);
                continue;
            }
            // Truncate huge fields so audit rows don't blow up
            if (is_string($v) && mb_strlen($v) > 4000) {
                $clean[$k] = mb_substr($v, 0, 4000) . '… [truncated]';
                continue;
            }
            $clean[$k] = $v;
        }
        return $clean;
    }
}
