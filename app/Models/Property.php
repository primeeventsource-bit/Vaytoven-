<?php

namespace App\Models;

use App\Enums\CancellationPolicy;
use App\Enums\FeeStructure;
use App\Enums\PropertyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'host_id',
        'listing_source',
        // Per-property Split-Fee / Single-Fee override. Without this in
        // $fillable a create()/update() would silently discard it.
        'fee_structure',
        'title',
        'description',
        'latitude',
        'longitude',
        'address_line',
        'city',
        'region',
        'country',
        'postal_code',
        'capacity',
        'bedrooms',
        'beds',
        'bathrooms',
        'price_cents',
        'listing_type',
        'cleaning_fee_cents',
        'cancellation_policy',
        'minimum_nights',
        'status',
        'payout_account_id',
        'converted_from_enquiry_id',

        // Listing builder. Absent from $fillable these are silently discarded
        // by create()/update() - the failure mode that made staff_notes look
        // like it saved when it did not.
        'reference',
        'property_kind',
        'resort_name',
        'member_service_order_id',
        'position_in_package',
        'location_precision',
        'square_feet',
        'floor_unit',
        'bed_configuration',
        'check_in_day',
        'check_in_time',
        'check_out_time',
        'unit_size_type',
        'view_type',
        'accessibility_notes',
        'pet_policy',
        'smoking_policy',
        'parking_info',
        'headline',
        'short_description',
        'highlights',
        'allow_offers',
        'allow_inquiries',
        'display_suggested_amount',
        'minimum_offer_cents',
        'require_guest_count',
        'require_message',
    ];

    protected function casts(): array
    {
        return [
            'highlights'               => 'array',
            'allow_offers'             => 'bool',
            'allow_inquiries'          => 'bool',
            'display_suggested_amount' => 'bool',
            'require_guest_count'      => 'bool',
            'require_message'          => 'bool',
            'square_feet'              => 'integer',
            'minimum_offer_cents'      => 'integer',
            'position_in_package'      => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'bathrooms' => 'decimal:1',
            'price_cents' => 'integer',
            'listing_type' => \App\Enums\ListingType::class,
            'cleaning_fee_cents' => 'integer',
            'capacity' => 'integer',
            'bedrooms' => 'integer',
            'beds' => 'integer',
            'minimum_nights' => 'integer',
            'status' => PropertyStatus::class,
            'cancellation_policy' => CancellationPolicy::class,
            'fee_structure' => FeeStructure::class,
            // Vacation Club Exchange Detection snapshot — written by
            // ExchangeDetectionObserver when listing_source = 'managed'.
            'exchange_detection' => 'array',
        ];
    }

    /**
     * Column defaults, declared here as well as in the schema.
     *
     * A database default is not applied to the instance create() hands back,
     * so code reading $property->location_precision immediately after creating
     * one got null and treated it as "no preference". Declaring it here means
     * the object and the row agree from the first line.
     */
    protected $attributes = [
        'location_precision'       => 'approximate',
        'allow_offers'             => true,
        'allow_inquiries'          => true,
        'display_suggested_amount' => false,
        'require_guest_count'      => true,
        'require_message'          => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $property) {
            $property->reference ??= static::generateReference();
        });
    }

    /**
     * A quotable reference, e.g. VAY-P-48213.
     *
     * Random rather than sequential, for the reason order and offer references
     * are: a sequential id published on a listing page tells anyone who looks
     * how many properties exist and lets them walk the set. The numeric shape
     * is kept because staff read these down a phone line.
     */
    public static function generateReference(): string
    {
        do {
            $reference = 'VAY-P-'.random_int(10000, 99999);
        } while (static::query()->where('reference', $reference)->exists());

        return $reference;
    }
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'property_amenities');
    }

    public function availabilityWeeks(): HasMany
    {
        return $this->hasMany(PropertyAvailabilityWeek::class)->orderBy('starts_on');
    }

    /** The paid order this advertisement is running under, if any. */
    public function memberServiceOrder(): BelongsTo
    {
        return $this->belongsTo(MemberServiceOrder::class, 'member_service_order_id');
    }

    /**
     * The price, written the way a visitor should read it.
     *
     * Every member program stay is seven days and six nights, so a rental
     * price is the price of that stay. Labelling it "per night" next to a
     * figure that covers the whole week is the ambiguity that ends in an
     * argument about what was advertised.
     */
    public function priceLabel(): string
    {
        return '$'.number_format((int) $this->price_cents / 100);
    }

    /** "7 days / 6 nights" for a rental, "Asking price" for a sale. */
    public function priceCaption(): string
    {
        return ($this->listing_type ?? \App\Enums\ListingType::Rent)->priceCaption();
    }

    public function isForSale(): bool
    {
        return ($this->listing_type ?? \App\Enums\ListingType::Rent)->isForSale();
    }

    /**
     * What goes in a URL for this listing.
     *
     * The owner's member number once one is set, the row id until then. Listings
     * that predate member ids keep working on their old address, and nothing has
     * to be backfilled before the feature is usable.
     */
    public function getRouteKey(): string
    {
        return (string) ($this->public_ref ?: $this->getKey());
    }

    /**
     * Resolve a listing from either address.
     *
     * The public ref is tried first, then the row id, so every URL ever handed
     * out keeps working — a link sent to a client last month resolves to the
     * same listing after a member number is added.
     */
    public function resolveRouteBinding($value, $field = null): ?static
    {
        if ($field !== null) {
            return $this->where($field, $value)->first();
        }

        return static::where('public_ref', $value)->first()
            ?? (ctype_digit((string) $value) ? static::find($value) : null);
    }

    /** "Property 2 of 3", or null when the listing is not tied to an order. */
    public function packagePosition(): ?string
    {
        $order = $this->memberServiceOrder;

        if (! $order || ! $this->position_in_package) {
            return null;
        }

        return sprintf(
            '%s Package — Property %d of %d',
            $order->package->label(),
            $this->position_in_package,
            max($order->package->propertyCount(), $this->position_in_package),
        );
    }

    /**
     * Coordinates as the public may see them.
     *
     * A member advertising a home they still live in has a real interest in
     * the pin not being their front door, so anything short of an explicit
     * "exact" is rounded to about a kilometre.
     */
    public function publicCoordinates(): ?array
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        if ($this->location_precision === 'exact') {
            return ['lat' => (float) $this->latitude, 'lng' => (float) $this->longitude];
        }

        if ($this->location_precision === 'city_only') {
            return null;
        }

        return [
            'lat' => round((float) $this->latitude, 2),
            'lng' => round((float) $this->longitude, 2),
        ];
    }
    public function photos(): HasMany
    {
        return $this->hasMany(PropertyPhoto::class)->orderBy('sort_order');
    }

    public function views(): HasMany
    {
        return $this->hasMany(PropertyView::class);
    }

    /**
     * The members-enquiry this listing was converted from (managed-listing path).
     * Null for normal host-listed properties (listing_source = 'host').
     */
    public function convertedFromEnquiry(): BelongsTo
    {
        return $this->belongsTo(MemberEnquiry::class, 'converted_from_enquiry_id');
    }
}
