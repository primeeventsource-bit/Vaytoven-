<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $table = 'admin_audit_logs';

    protected $fillable = [
        'admin_user_id', 'action',
        'target_type', 'target_id', 'target_uuid',
        'before_state', 'after_state',
        'reason', 'ip_address', 'user_agent',
        'created_at',
    ];

    protected $casts = [
        'before_state' => 'array',
        'after_state'  => 'array',
        'created_at'   => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeByAdmin(Builder $q, int $userId): Builder
    {
        return $q->where('admin_user_id', $userId);
    }

    public function scopeForTarget(Builder $q, string $type, int|string $id): Builder
    {
        $q->where('target_type', $type);

        return is_int($id)
            ? $q->where('target_id', $id)
            : $q->where('target_uuid', $id);
    }

    public function scopeAction(Builder $q, string $action): Builder
    {
        // Supports prefix matching: scope: 'members_enquiry.' matches all members_enquiry.* actions
        return str_ends_with($action, '.')
            ? $q->where('action', 'like', $action . '%')
            : $q->where('action', $action);
    }

    /**
     * Build a compact diff between before/after, only showing keys that actually changed.
     */
    public function diff(): array
    {
        $before = $this->before_state ?? [];
        $after  = $this->after_state  ?? [];
        $diff = [];
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        foreach ($keys as $k) {
            $b = $before[$k] ?? null;
            $a = $after[$k]  ?? null;
            if ($b !== $a) $diff[$k] = ['before' => $b, 'after' => $a];
        }
        return $diff;
    }
}
