<?php

namespace App\Services\Members;

use App\Enums\ActivityType;
use App\Models\User;
use App\Services\Tracking\ActivityRecorder;
use App\Support\Location\AddressGeocoder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * The one place a member's address on file changes.
 *
 * Whoever changes it — the member on their profile, or staff on their behalf
 * — the change is an important account change: it is geocoded once, and an
 * activity row records who changed it, from where, and which fields. The
 * address itself goes on the member's own evidence rows as they happen; this
 * row records the change.
 */
class AddressOnFileUpdater
{
    public const FIELDS = [
        'address_line1', 'address_line2', 'address_city', 'address_state',
        'address_postal_code', 'address_country',
    ];

    public function __construct(
        private readonly AddressGeocoder $geocoder,
        private readonly ActivityRecorder $activity,
    ) {
    }

    /** @return array<string, mixed> validation rules for the address inputs */
    public static function rules(): array
    {
        return [
            'address_line1'       => ['nullable', 'string', 'max:255'],
            'address_line2'       => ['nullable', 'string', 'max:255'],
            'address_city'        => ['nullable', 'string', 'max:128'],
            'address_state'       => ['nullable', 'string', 'max:64'],
            'address_postal_code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9 \-]{3,20}$/'],
            'address_country'     => ['nullable', 'string', 'size:2', 'alpha'],
        ];
    }

    /**
     * Applies the address inputs, if present, and saves.
     *
     * @param  array<string, mixed>  $input  validated request data
     * @return list<string> the address fields that changed
     */
    public function apply(User $member, array $input, ?User $actor, ?Request $request = null): array
    {
        $present = Arr::only($input, self::FIELDS);

        if ($present === []) {
            return [];
        }

        foreach ($present as $field => $value) {
            $value = is_string($value) ? trim($value) : $value;
            $member->{$field} = match ($field) {
                'address_country' => $value ? strtoupper($value) : ($member->address_line1 || $value ? 'US' : null),
                'address_state'   => $value ? (strlen($value) === 2 ? strtoupper($value) : $value) : null,
                default           => $value ?: null,
            };
        }

        if (($present['address_line1'] ?? $member->address_line1) && ! $member->address_country) {
            $member->address_country = 'US';
        }

        $changed = array_values(array_filter(self::FIELDS, fn ($f) => $member->isDirty($f)));

        if ($changed === []) {
            return [];
        }

        $this->geocoder->geocode($member);
        $member->save();

        $this->activity->record(
            ActivityType::ProfileUpdated,
            $request,
            subjectType: 'user',
            subjectReference: (string) $member->id,
            result: 'completed',
            // Field names only. The address is personal data and is already on
            // the account; a copy here would outlive a later correction.
            metadata: ['changed' => $changed, 'change' => 'address_on_file', 'member_user_id' => $member->id],
            actor: $actor,
        );

        return $changed;
    }
}
