<?php

namespace Database\Seeders;

use App\Enums\CancellationPolicy;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Curated property catalogue for the launch surface (FR-2.x).
 *
 * Lays down a single demo host and roughly two listings per destination card
 * on the landing — enough to make the browse grid look populated without
 * editorialising 100 fictional properties. Photos are Unsplash CDN urls
 * picked to match each property's theme; the same source used by the
 * landing's destination cards so the visual language stays consistent.
 *
 * Idempotent: keyed by (host, title) — re-runs are a no-op.
 */
class PropertySeeder extends Seeder
{
    public function run(): void
    {
        $host = User::firstOrCreate(
            ['email' => 'demo-host@vaytoven.com'],
            [
                'name'              => 'Demo Host',
                'password'          => Hash::make('not-a-real-account-' . bin2hex(random_bytes(8))),
                'role'              => UserRole::Host,
                'email_verified_at' => now(),
            ],
        );

        $standardAmenities = Amenity::whereIn('slug', [
            'wifi', 'air-conditioning', 'kitchen', 'tv', 'self-checkin',
            'smoke-alarm', 'co-detector', 'first-aid-kit',
        ])->pluck('id', 'slug');

        $byCity = [
            'beachfront' => Amenity::where('slug', 'beachfront')->value('id'),
            'pool'       => Amenity::where('slug', 'pool')->value('id'),
            'hot-tub'    => Amenity::where('slug', 'hot-tub')->value('id'),
            'mountain'   => Amenity::where('slug', 'mountain-view')->value('id'),
            'lakefront'  => Amenity::where('slug', 'lakefront')->value('id'),
            'fireplace'  => Amenity::where('slug', 'fireplace')->value('id'),
            'workspace'  => Amenity::where('slug', 'dedicated-workspace')->value('id'),
            'fast-wifi'  => Amenity::where('slug', 'fast-wifi')->value('id'),
        ];

        foreach ($this->catalogue() as $row) {
            $property = Property::updateOrCreate(
                [
                    'host_id' => $host->id,
                    'title'   => $row['title'],
                ],
                [
                    'listing_source'     => 'host',
                    'description'        => $row['description'],
                    'latitude'           => $row['latitude'],
                    'longitude'          => $row['longitude'],
                    'address_line'       => $row['address_line'] ?? '—',
                    'city'               => $row['city'],
                    'region'             => $row['region'] ?? null,
                    'country'            => $row['country'],
                    'postal_code'        => $row['postal_code'] ?? null,
                    'capacity'           => $row['capacity'],
                    'bedrooms'           => $row['bedrooms'],
                    'beds'               => $row['beds'],
                    'bathrooms'          => $row['bathrooms'],
                    'price_cents' => $row['price_cents'],
                    'cleaning_fee_cents' => $row['cleaning_fee_cents'] ?? 6000,
                    'cancellation_policy'=> $row['cancellation_policy']->value,
                    'minimum_nights'     => $row['minimum_nights'] ?? 2,
                    'status'             => PropertyStatus::Active->value,
                ],
            );

            // Attach standard amenities + the city-specific ones requested.
            $amenityIds = $standardAmenities->values()->all();
            foreach ($row['extra_amenities'] ?? [] as $slug) {
                if (isset($byCity[$slug])) {
                    $amenityIds[] = $byCity[$slug];
                }
            }
            $property->amenities()->sync(array_values(array_filter($amenityIds)));

            // Attach photos. Idempotent — clear and re-add in seed order.
            $property->photos()->delete();
            foreach ($row['photos'] as $i => $url) {
                PropertyPhoto::create([
                    'property_id' => $property->id,
                    'url'         => $url,
                    'sort_order'  => $i,
                    'caption'     => $row['title'],
                ]);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalogue(): array
    {
        return [
            // ── Bali ──────────────────────────────────────────────────────
            [
                'title'              => 'Ubud Jungle Villa with Plunge Pool',
                'description'        => 'A modern open-plan villa nestled into the Ubud rice terraces. Vaulted ceilings, an outdoor shower, and a small private plunge pool overlooking the gorge. Quick scooter ride to Ubud centre and the monkey forest.',
                'latitude'           => -8.5069,
                'longitude'          => 115.2625,
                'city'               => 'Bali',
                'region'             => 'Ubud',
                'country'            => 'ID',
                'capacity'           => 4,
                'bedrooms'           => 2,
                'beds'               => 2,
                'bathrooms'          => 2.0,
                'price_cents' => 14800,
                'cancellation_policy'=> CancellationPolicy::Moderate,
                'minimum_nights'     => 3,
                'extra_amenities'    => ['pool'],
                'photos' => [
                    'https://images.unsplash.com/photo-1537996194471-e657df975ab4?w=1400&q=80',
                    'https://images.unsplash.com/photo-1540541338287-41700207dee6?w=1400&q=80',
                    'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?w=1400&q=80',
                ],
            ],
            [
                'title'              => 'Seminyak Beachfront Bungalow',
                'description'        => 'Two-bedroom bungalow steps from the surf at Seminyak. Beachfront balcony, breakfast included, walking distance to the beach clubs.',
                'latitude'           => -8.6905,
                'longitude'          => 115.1729,
                'city'               => 'Bali',
                'region'             => 'Seminyak',
                'country'            => 'ID',
                'capacity'           => 4,
                'bedrooms'           => 2,
                'beds'               => 3,
                'bathrooms'          => 2.0,
                'price_cents' => 22400,
                'cancellation_policy'=> CancellationPolicy::Flexible,
                'minimum_nights'     => 2,
                'extra_amenities'    => ['beachfront', 'pool'],
                'photos' => [
                    'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=1400&q=80',
                    'https://images.unsplash.com/photo-1499793983690-e29da59ef1c2?w=1400&q=80',
                ],
            ],

            // ── Santorini ────────────────────────────────────────────────
            [
                'title'              => 'Oia Caldera View Suite',
                'description'        => 'Iconic whitewashed cave suite carved into the Oia cliffside. Sunset views from the private terrace, soaking tub indoors, walking distance to Ammoudi Bay.',
                'latitude'           => 36.4618,
                'longitude'          => 25.3753,
                'city'               => 'Santorini',
                'region'             => 'Oia',
                'country'            => 'GR',
                'capacity'           => 2,
                'bedrooms'           => 1,
                'beds'               => 1,
                'bathrooms'          => 1.0,
                'price_cents' => 38500,
                'cancellation_policy'=> CancellationPolicy::Strict,
                'minimum_nights'     => 3,
                'extra_amenities'    => [],
                'photos' => [
                    'https://images.unsplash.com/photo-1570077188670-e3a8d69ac5ff?w=1400&q=80',
                    'https://images.unsplash.com/photo-1601581875309-fafbf2d3ed3a?w=1400&q=80',
                ],
            ],
            [
                'title'              => 'Imerovigli Cliffside Villa',
                'description'        => 'Three-bedroom villa with infinity pool perched above the caldera. Sleeps six comfortably, full kitchen, daily housekeeping included.',
                'latitude'           => 36.4380,
                'longitude'          => 25.4243,
                'city'               => 'Santorini',
                'region'             => 'Imerovigli',
                'country'            => 'GR',
                'capacity'           => 6,
                'bedrooms'           => 3,
                'beds'               => 3,
                'bathrooms'          => 2.5,
                'price_cents' => 64000,
                'cleaning_fee_cents' => 12000,
                'cancellation_policy'=> CancellationPolicy::Strict,
                'minimum_nights'     => 4,
                'extra_amenities'    => ['pool'],
                'photos' => [
                    'https://images.unsplash.com/photo-1601581875309-fafbf2d3ed3a?w=1400&q=80',
                    'https://images.unsplash.com/photo-1533105079780-92b9be482077?w=1400&q=80',
                ],
            ],

            // ── Lake Tahoe ────────────────────────────────────────────────
            [
                'title'              => 'South Lake Tahoe Cabin with Hot Tub',
                'description'        => 'A-frame cabin in the pines, ten minutes from Heavenly. Wood-burning fireplace, outdoor hot tub, ski-in/ski-out access via shuttle in winter.',
                'latitude'           => 38.9399,
                'longitude'          => -119.9772,
                'city'               => 'Lake Tahoe',
                'region'             => 'South Shore',
                'country'            => 'US',
                'capacity'           => 6,
                'bedrooms'           => 3,
                'beds'               => 4,
                'bathrooms'          => 2.0,
                'price_cents' => 18900,
                'cancellation_policy'=> CancellationPolicy::Moderate,
                'minimum_nights'     => 2,
                'extra_amenities'    => ['hot-tub', 'fireplace', 'mountain'],
                'photos' => [
                    'https://images.unsplash.com/photo-1551524559-8af4e6624178?w=1400&q=80',
                    'https://images.unsplash.com/photo-1499793983690-e29da59ef1c2?w=1400&q=80',
                ],
            ],
            [
                'title'              => 'Lakefront Modern with Private Dock',
                'description'        => 'Architect-designed lakefront stay with a private dock, kayaks included, walls of glass facing the water. Sleeps eight; ideal for two families.',
                'latitude'           => 39.0968,
                'longitude'          => -120.0324,
                'city'               => 'Lake Tahoe',
                'region'             => 'North Shore',
                'country'            => 'US',
                'capacity'           => 8,
                'bedrooms'           => 4,
                'beds'               => 5,
                'bathrooms'          => 3.0,
                'price_cents' => 42500,
                'cleaning_fee_cents' => 15000,
                'cancellation_policy'=> CancellationPolicy::Strict,
                'minimum_nights'     => 3,
                'extra_amenities'    => ['lakefront', 'fireplace'],
                'photos' => [
                    'https://images.unsplash.com/photo-1519302959554-a75be0afc82a?w=1400&q=80',
                    'https://images.unsplash.com/photo-1502602898657-3e91760cbb34?w=1400&q=80',
                ],
            ],

            // ── Paris ─────────────────────────────────────────────────────
            [
                'title'              => 'Le Marais Apartment near Place des Vosges',
                'description'        => 'Top-floor apartment in the heart of the 4e. Beamed ceilings, 19th-century details, two minutes walk to the Place des Vosges and a short metro hop to the Louvre.',
                'latitude'           => 48.8557,
                'longitude'          => 2.3637,
                'city'               => 'Paris',
                'region'             => 'Le Marais',
                'country'            => 'FR',
                'capacity'           => 4,
                'bedrooms'           => 2,
                'beds'               => 2,
                'bathrooms'          => 1.0,
                'price_cents' => 21800,
                'cancellation_policy'=> CancellationPolicy::Moderate,
                'minimum_nights'     => 3,
                'extra_amenities'    => ['workspace', 'fast-wifi'],
                'photos' => [
                    'https://images.unsplash.com/photo-1502602898657-3e91760cbb34?w=1400&q=80',
                    'https://images.unsplash.com/photo-1549144511-f099e773c147?w=1400&q=80',
                ],
            ],
            [
                'title'              => '7e Eiffel-View Pied-à-Terre',
                'description'        => 'Quiet one-bedroom on the rive gauche with direct Eiffel Tower view from the salon. Walking distance to Champ de Mars and the Rodin museum.',
                'latitude'           => 48.8566,
                'longitude'          => 2.3052,
                'city'               => 'Paris',
                'region'             => '7e arrondissement',
                'country'            => 'FR',
                'capacity'           => 2,
                'bedrooms'           => 1,
                'beds'               => 1,
                'bathrooms'          => 1.0,
                'price_cents' => 28800,
                'cancellation_policy'=> CancellationPolicy::Strict,
                'minimum_nights'     => 4,
                'extra_amenities'    => [],
                'photos' => [
                    'https://images.unsplash.com/photo-1549144511-f099e773c147?w=1400&q=80',
                    'https://images.unsplash.com/photo-1431274172761-fca41d930114?w=1400&q=80',
                ],
            ],

            // ── Tokyo ─────────────────────────────────────────────────────
            [
                'title'              => 'Shibuya Tower Studio with Skyline View',
                'description'        => '34th-floor studio over Shibuya crossing. Quiet despite the location thanks to triple-glazed glass. Walk to Harajuku, Yoyogi Park, and the Shibuya Sky observation deck.',
                'latitude'           => 35.6595,
                'longitude'          => 139.7005,
                'city'               => 'Tokyo',
                'region'             => 'Shibuya',
                'country'            => 'JP',
                'capacity'           => 2,
                'bedrooms'           => 1,
                'beds'               => 1,
                'bathrooms'          => 1.0,
                'price_cents' => 14200,
                'cancellation_policy'=> CancellationPolicy::Flexible,
                'minimum_nights'     => 2,
                'extra_amenities'    => ['workspace', 'fast-wifi'],
                'photos' => [
                    'https://images.unsplash.com/photo-1503899036084-c55cdd92da26?w=1400&q=80',
                    'https://images.unsplash.com/photo-1542051841857-5f90071e7989?w=1400&q=80',
                ],
            ],
            [
                'title'              => 'Shimokitazawa Loft Apartment',
                'description'        => 'Light-filled loft in a quieter Tokyo neighbourhood famous for record stores and small theatres. Two bedrooms, full kitchen, twelve minutes by train to Shinjuku.',
                'latitude'           => 35.6614,
                'longitude'          => 139.6675,
                'city'               => 'Tokyo',
                'region'             => 'Shimokitazawa',
                'country'            => 'JP',
                'capacity'           => 4,
                'bedrooms'           => 2,
                'beds'               => 2,
                'bathrooms'          => 1.0,
                'price_cents' => 16500,
                'cancellation_policy'=> CancellationPolicy::Moderate,
                'minimum_nights'     => 3,
                'extra_amenities'    => [],
                'photos' => [
                    'https://images.unsplash.com/photo-1542051841857-5f90071e7989?w=1400&q=80',
                    'https://images.unsplash.com/photo-1503899036084-c55cdd92da26?w=1400&q=80',
                ],
            ],
        ];
    }
}
