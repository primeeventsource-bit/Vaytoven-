<?php

namespace App\Services\Activity;

use App\Enums\ActivityType;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Activity Center's reader.
 *
 * Every filter is applied in SQL. The obvious alternative — fetch and filter in
 * PHP — works fine on a demo database and falls over on the one that matters,
 * and it makes the row count at the top of the page a lie.
 *
 * Nothing here writes. tracking_events is append-only by design, and the
 * reading side has no business being able to change what it reads.
 */
class ActivityLogQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->build($filters)
            ->with('actor:id,name,first_name,last_name,email,role')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * One visit, oldest first.
     *
     * Ascending on purpose: a journey read backwards is not a journey. This is
     * the "Entered site → Search → Property → Gallery → Offer" view.
     */
    public function session(string $sessionId): \Illuminate\Support\Collection
    {
        return TrackingEvent::query()
            ->where('session_id', $sessionId)
            ->with('actor:id,name,first_name,last_name,email,role')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Counts per filter tab, so the tabs can carry numbers.
     *
     * Computed under the SAME filters as the list, minus the group itself.
     * Tabs that ignored the date range would send staff to an empty page.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function groupCounts(array $filters): array
    {
        $counts = [];

        foreach (array_keys(ActivityType::groups()) as $group) {
            $counts[$group] = (clone $this->build($filters + [], $group))->count();
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function build(array $filters, ?string $groupOverride = null): Builder
    {
        $query = TrackingEvent::query();

        $group = $groupOverride ?? ($filters['group'] ?? 'all');

        if ($group !== 'all') {
            $values = ActivityType::valuesForGroup($group);
            // An unknown group must return nothing rather than everything. A
            // typo in a URL should not quietly widen an audit view.
            $query->whereIn('event_type', $values ?: ['__none__']);
        }

        if (! empty($filters['type'])) {
            $query->where('event_type', $filters['type']);
        }

        if (! empty($filters['ip'])) {
            $query->where('ip_address', 'like', trim($filters['ip']).'%');
        }

        if (! empty($filters['session'])) {
            $query->where('session_id', trim($filters['session']));
        }

        if (! empty($filters['subject'])) {
            $query->where('subject_reference', trim($filters['subject']));
        }

        if (! empty($filters['country'])) {
            $query->where('country', strtoupper(trim($filters['country'])));
        }

        if (! empty($filters['city'])) {
            $query->where('city', 'like', trim($filters['city']).'%');
        }

        if (! empty($filters['result'])) {
            $query->where('result', $filters['result']);
        }

        if (! empty($filters['device'])) {
            $query->where('device_type', $filters['device']);
        }

        // A member is identified by id or by email. Staff have the email in
        // front of them far more often than the id.
        if (! empty($filters['user'])) {
            $needle = trim($filters['user']);

            $userIds = is_numeric($needle)
                ? [(int) $needle]
                : User::query()
                    ->where('email', 'like', "%{$needle}%")
                    ->orWhere('name', 'like', "%{$needle}%")
                    ->limit(50)
                    ->pluck('id')
                    ->all();

            $query->whereIn('actor_user_id', $userIds ?: [0]);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('occurred_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('occurred_at', '<=', $filters['to']);
        }

        return $query;
    }
}
