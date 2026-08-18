<?php

namespace Tests\Feature\Listings;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A listing names its owner as "John S.", never in full.
 *
 * The page already carries a location and the dates the property is empty. A
 * full surname on top of that is the piece that makes the set identifying, and
 * it buys the reader nothing — the point is to feel dealt with by a person,
 * not to know which person.
 */
class OwnerDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    private function owner(array $attributes): User
    {
        return User::factory()->create($attributes);
    }

    public function test_it_renders_a_first_name_and_an_initial(): void
    {
        $owner = $this->owner(['first_name' => 'John', 'last_name' => 'Smith', 'name' => 'John Smith']);

        $this->assertSame('John S.', $owner->publicDisplayName());
    }

    /** Legacy rows kept a single name field; split it rather than print it. */
    public function test_a_single_name_field_is_split_rather_than_shown_whole(): void
    {
        $owner = $this->owner(['first_name' => null, 'last_name' => null, 'name' => 'Margaret Whitfield']);

        $this->assertSame('Margaret W.', $owner->publicDisplayName());
    }

    public function test_a_middle_name_does_not_become_the_initial(): void
    {
        $owner = $this->owner(['first_name' => null, 'last_name' => null, 'name' => 'Ada Marie Lovelace']);

        $this->assertSame('Ada L.', $owner->publicDisplayName());
    }

    public function test_a_first_name_alone_shows_no_stray_initial(): void
    {
        $owner = $this->owner(['first_name' => 'Prince', 'last_name' => null, 'name' => 'Prince']);

        $this->assertSame('Prince', $owner->publicDisplayName());
    }

    /** Never an empty line, and never an email address as a substitute. */
    public function test_a_nameless_owner_gets_a_neutral_label(): void
    {
        $owner = $this->owner(['first_name' => null, 'last_name' => null, 'name' => '']);

        $this->assertSame('Property owner', $owner->publicDisplayName());
        $this->assertStringNotContainsString('@', $owner->publicDisplayName());
    }

    /**
     * mb_substr, not substr: a surname starting with a multi-byte character
     * would otherwise be cut mid-character and render as a replacement box.
     */
    public function test_a_multibyte_surname_produces_a_whole_character(): void
    {
        $owner = $this->owner(['first_name' => 'Élodie', 'last_name' => 'Ångström', 'name' => 'Élodie Ångström']);

        $this->assertSame('Élodie Å.', $owner->publicDisplayName());
    }

    public function test_the_initial_is_capitalised(): void
    {
        $owner = $this->owner(['first_name' => 'John', 'last_name' => 'smith', 'name' => 'John smith']);

        $this->assertSame('John S.', $owner->publicDisplayName());
    }

    // --- the public page --------------------------------------------------------

    public function test_the_listing_page_shows_the_short_name_and_not_the_surname(): void
    {
        $owner = $this->owner([
            'first_name' => 'John', 'last_name' => 'Smith', 'name' => 'John Smith',
        ]);

        $property = Property::factory()->create([
            'host_id' => $owner->id,
            'status'  => PropertyStatus::Active->value,
        ]);

        $response = $this->get(route('properties.show', $property))->assertOk();

        $response->assertSee('John S.');
        $response->assertDontSee('John Smith');
        $response->assertDontSee($owner->email);
    }

    public function test_the_listing_page_never_exposes_the_owner_email(): void
    {
        $owner = $this->owner([
            'first_name' => 'Dana', 'last_name' => 'Whitfield',
            'name' => 'Dana Whitfield', 'email' => 'dana.whitfield@example.com',
        ]);

        $property = Property::factory()->create([
            'host_id' => $owner->id,
            'status'  => PropertyStatus::Active->value,
        ]);

        $body = $this->get(route('properties.show', $property))->assertOk()->getContent();

        $this->assertStringNotContainsString('dana.whitfield@example.com', $body);
        $this->assertStringNotContainsString('Whitfield', $body);
    }
}
