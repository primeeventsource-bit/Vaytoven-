<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Centralised admin-action audit log writer (FR-1.5, FR-7.x).
 *
 * Every privileged write triggered by an admin must call AdminAuditLogService::log()
 * — the rule is in AGENTS.md.
 */
class AdminAuditLogService
{
    public static function log(
        User $actor,
        string $action,
        ?Model $subject = null,
        ?array $payload = null,
        ?string $ipAddress = null,
    ): AdminAuditLog {
        return AdminAuditLog::create([
            'actor_user_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'payload' => $payload,
            'ip_address' => $ipAddress,
            'occurred_at' => now(),
        ]);
    }
}
