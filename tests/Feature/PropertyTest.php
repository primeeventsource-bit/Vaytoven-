<?php

namespace Tests\Feature;

use App\Enums\CancellationPolicy;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\User;
use Database\Seeders\AmenitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_belongs_to_host(): void
    {
        $host = User::factory()->create(['role' => UserRole::Host]);
        $property = Property::factory()->create(['host_id' => $host->id]);

        $this->assertTrue($property->host->is($host));
        $this->assertCount(1, $host->hostProperties);
    }

    public function test_property_has_many_photos_ordered_by_sort(): void
    {
        $property = Property::factory()->create();
        PropertyPhoto::factory()->count(3)->sequence(
            ['sort_order' => 2, 'caption' => 'second'],
            ['sort_order' => 0, 'caption' => 'first'],
            ['sort_order' => 1, 'caption' => 'middle'],
        )->create(['property_id' => $property->id]);

        $captions = $property->photos->pluck('caption')->toArray();
        $this->assertSame(['first', 'middle', 'second'], $captions);
    }

    public function test_property_has_many_amenities(): void
    {
        $property = Property::factory()->create();
        $a = Amenity::factory()->create(['slug' => 'wifi-test']);
        $b = Amenity::factory()->create(['slug' => 'pool-test']);
        $property->amenities()->attach([$a->id, $b->id]);

        $this->assertCount(2, $property->fresh()->amenities);
    }

    public function test_money_fields_are_integer_cents(): void
    {
        $property = Property::factory()->create([
            'base_nightly_cents' => 12500,    // $125.00
            'cleaning_fee_cents' => 5000,     // $50.00
        ]);

        $this->assertSame(12500, $property->base_nightly_cents);
        $this->assertSame(5000, $property->cleaning_fee_cents);
        $this->assertIsInt($property->base_nightly_cents);
    }

    public function test_status_and_policy_cast_to_enums(): void
    {
        $property = Property::factory()->create([
            'status' => PropertyStatus::Active->value,
            'cancellation_policy' => CancellationPolicy::Strict->value,
        ]);

        $this->assertSame(PropertyStatus::Active, $property->status);
        $this->assertSame(CancellationPolicy::Strict, $property->cancellation_policy);
        $this->assertTrue($property->status->isPublic());
    }

    public function test_default_status_is_draft(): void
    {
        $host = User::factory()->create(['role' => UserRole::Host]);
        $property = Property::create([
            'host_id' => $host->id,
            'title' => 'Untitled cabin',
            'latitude' => 40.7,
            'longitude' => -74.0,
            'base_nightly_cents' => 10000,
        ]);

        $this->assertSame(PropertyStatus::Draft, $property->fresh()->status);
    }

    public function test_amenities_seeder_populates_canonical_catalogue(): void
    {
        $this->seed(AmenitiesSeeder::class);

        $this->assertGreaterThanOrEqual(50, Amenity::count());
        $this->assertDatabaseHas('amenities', ['slug' => 'wifi']);
        $this->assertDatabaseHas('amenities', ['slug' => 'pool']);
        $this->assertDatabaseHas('amenities', ['slug' => 'pets-allowed']);
    }
}
