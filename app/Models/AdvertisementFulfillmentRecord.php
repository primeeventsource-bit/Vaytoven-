<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * One evidentiary fact about a member and their advertisement.
 *
 * Append-only. The database refuses UPDATE and DELETE on MySQL; this observer
 * does the same on SQLite and for anything that reaches the model first. A
 * mistake is answered with a correction row, never by changing the original.
 *
 * Every row carries record_hash over its own content, so an altered row is
 * detectable even by someone who went around the trigger. It is deliberately
 * NOT chained to the previous row: a chain couples every member's evidence to
 * everyone else's, and one bad row would cast doubt on all of them.
 */
class AdvertisementFulfillmentRecord extends Model
{
    public const EVENT_FIRST_ACCESS = 'first_access';
    public const EVENT_ACCEPTED     = 'accepted';
    public const EVENT_CORRECTION   = 'correction';

    public const EVENT_INCENTIVE_PRESENTED    = 'incentive_presented';
    public const EVENT_INCENTIVE_ACKNOWLEDGED = 'incentive_acknowledged';
    public const EVENT_INCENTIVE_DELIVERED    = 'incentive_delivered';

    public const SOURCE_LIVE     = 'live';
    public const SOURCE_BACKFILL = 'backfill:tracking_event';

    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * The columns that make up the fact. record_hash covers exactly these, in
     * this order — adding a column here changes the hash of new rows only.
     */
    public const HASHED = [
        'record_uuid', 'event', 'dedupe_key', 'source', 'source_tracking_event_id',
        'incentive_key', 'incentive_name', 'incentive_value', 'incentive_provider', 'incentive_version',
        'incentive_presentation_hash', 'delivery_method', 'delivery_reference',
        'property_id', 'property_reference', 'advertisement_url', 'advertising_period_id',
        'member_service_order_id', 'order_reference', 'package', 'advertisement_activated_at',
        'user_id', 'member_number', 'member_name', 'member_email', 'member_role',
        'address_line1', 'address_line2', 'address_city', 'address_state', 'address_postal_code', 'address_country',
        'first_login_at', 'first_login_event_id', 'first_login_session_id',
        'acknowledgement_version', 'acknowledgement_hash',
        'corrects_record_id', 'correction_note', 'recorded_by_user_id',
        'ip_address', 'country', 'region', 'city', 'latitude', 'longitude',
        'location_comparison', 'location_distance_miles', 'location_comparison_reason',
        'device_type', 'browser', 'platform',
        'session_id', 'user_agent', 'referrer_host', 'path', 'tracking_event_id',
        'occurred_at', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'advertisement_activated_at' => 'datetime',
            'first_login_at'             => 'datetime',
            'occurred_at'                => 'datetime',
            'recorded_at'                => 'datetime',
            'metadata'                   => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            $record->record_uuid ??= (string) Str::uuid();
            // Set explicitly, not left to the column default: the hash is taken
            // now, and a default applied by the database would read back as a
            // different value than the one hashed.
            $record->source ??= self::SOURCE_LIVE;
            $record->recorded_at ??= now();
            $record->occurred_at ??= $record->recorded_at;
            $record->record_hash = $record->computeHash();
        });

        static::updating(function (): void {
            throw new RuntimeException('advertisement_fulfillment_records is append-only — UPDATE not allowed');
        });

        static::deleting(function (): void {
            throw new RuntimeException('advertisement_fulfillment_records is append-only — DELETE not allowed');
        });
    }

    public function computeHash(): string
    {
        $values = [];

        // Decimal columns at their stored scale: MySQL hands back "25.761700"
        // for what was written as 25.7617, and the hash must not care.
        $scales = ['latitude' => 6, 'longitude' => 6, 'location_distance_miles' => 1];

        foreach (self::HASHED as $column) {
            $value = $this->getAttribute($column);

            $values[$column] = match (true) {
                $value === null                     => null,
                $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
                isset($scales[$column])             => number_format((float) $value, $scales[$column], '.', ''),
                default                             => (string) $value,
            };
        }

        return hash('sha256', json_encode($values, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** True while the stored row still matches its own hash. */
    public function verifies(): bool
    {
        return hash_equals((string) $this->record_hash, $this->computeHash());
    }

    public function addressOnFile(): \App\Support\Location\AddressOnFile
    {
        return new \App\Support\Location\AddressOnFile(
            line1: $this->address_line1, line2: $this->address_line2, city: $this->address_city,
            state: $this->address_state, postalCode: $this->address_postal_code, country: $this->address_country,
        );
    }

    public function location(): ?string
    {
        return collect([$this->city, $this->region, $this->country])->filter()->implode(', ') ?: null;
    }
}
