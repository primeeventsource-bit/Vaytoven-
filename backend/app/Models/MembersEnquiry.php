<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembersEnquiry extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'members_enquiries';

    public const STATUSES = [
        'new',
        'contacted',
        'qualified',
        'onboarded',
        'rejected',
        'unresponsive',
        'duplicate',
    ];

    public const SOURCES = [
        'website',
        'app_search',
        'app_host',
        'admin',
        'import',
    ];

    public const PROGRAMS = [
        'Marriott Vacation Club',
        'Hilton Grand Vacations',
        'Disney Vacation Club',
        'Wyndham Destinations',
        'Hyatt Residence Club',
        'Diamond Resorts',
        'Worldmark by Wyndham',
        'Bluegreen Vacations',
        'Westgate',
        'RCI Points',
        'Interval International',
        'Other / Independent',
    ];

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone',
        'program', 'property', 'annual_points', 'best_time_to_call', 'notes',
        'consent_given', 'consent_at', 'source',
        'referrer_url', 'user_agent', 'ip_address',
        'status', 'assigned_to', 'qualified_at', 'onboarded_at',
        'rejection_reason', 'converted_property_id',
        'spam_score', 'flagged',
    ];

    protected $casts = [
        'consent_given'  => 'boolean',
        'consent_at'     => 'datetime',
        'qualified_at'   => 'datetime',
        'onboarded_at'   => 'datetime',
        'flagged'        => 'boolean',
        'spam_score'     => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function convertedProperty(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'converted_property_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', ['new', 'contacted', 'qualified']);
    }

    public function scopeAssignedTo(Builder $q, int $userId): Builder
    {
        return $q->where('assigned_to', $userId);
    }

    public function scopeUnflagged(Builder $q): Builder
    {
        return $q->where('flagged', false);
    }

    public function scopeFromSource(Builder $q, string $source): Builder
    {
        return $q->where('source', $source);
    }

    // ─── State transitions (helpers; auditing happens in service layer) ──

    public function markContacted(): void
    {
        $this->update(['status' => 'contacted']);
    }

    public function markQualified(): void
    {
        $this->update([
            'status'       => 'qualified',
            'qualified_at' => now(),
        ]);
    }

    public function markOnboarded(?string $convertedPropertyId = null): void
    {
        $this->update([
            'status'                => 'onboarded',
            'onboarded_at'          => now(),
            'converted_property_id' => $convertedPropertyId,
        ]);
    }

    public function markRejected(string $reason): void
    {
        $this->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
        ]);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Mask the email for display in lists where the assignee shouldn't see it raw
     * (matches the same masking pattern as User::maskedEmail()).
     */
    public function maskedEmail(): string
    {
        $parts = explode('@', $this->email);
        if (count($parts) !== 2) {
            return $this->email;
        }
        $local  = $parts[0];
        $masked = substr($local, 0, 1) . str_repeat('•', max(1, strlen($local) - 1));
        return $masked . '@' . $parts[1];
    }
}
