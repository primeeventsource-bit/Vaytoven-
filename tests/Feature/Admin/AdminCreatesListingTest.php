<?php

namespace Tests\Feature\Admin;

use App\Mail\ListingCreatedForOwner;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Staff building a listing for an owner who is on the phone.
 *
 * Two properties matter beyond "it saves": the owner is always told, and if an
 * account had to be created for them, the password staff issued is single-use.
 */
class AdminCreatesListingTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $roleKey = 'super_admin'): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create(['must_change_password' => false]);
        $user->roles()->sync([Role::where('key', $roleKey)->firstOrFail()->id]);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'owner_mode'      => 'new',
            'owner_first_name'=> 'Marguerite',
            'owner_last_name' => 'Olsen',
            'owner_email'     => 'marguerite@example.com',
            'owner_phone'     => '+1 555 555 0144',
            'title'           => 'Cliffside Cottage',
            'description'     => 'Two bedrooms above the bay.',
            'city'            => 'Mendocino',
            'region'          => 'CA',
            'country'         => 'us',
            'capacity'        => 4,
            'bedrooms'        => 2,
            'beds'            => 3,
            'bathrooms'       => 1.5,
            'price_dollars' => '249.99',
            'listing_type'    => 'rent',
            'minimum_nights'  => 2,
            'status'          => 'draft',
            'listing_source'  => 'host',
            'notify_owner'    => 1,
        ], $overrides);
    }

    // --- permissions ------------------------------------------------------

    public function test_a_super_admin_can_reach_the_create_screen(): void
    {
        $this->actingAs($this->staff('super_admin'))
            ->get(route('admin.properties.create'))->assertOk();
    }

    public function test_an_admin_can_reach_the_create_screen(): void
    {
        $this->actingAs($this->staff('admin'))
            ->get(route('admin.properties.create'))->assertOk();
    }

    public function test_a_role_without_the_permission_cannot(): void
    {
        $this->actingAs($this->staff('customer_service'))
            ->get(route('admin.properties.create'))->assertForbidden();
    }

    public function test_a_signed_out_visitor_cannot(): void
    {
        $this->get(route('admin.properties.create'))->assertRedirect(route('login'));
    }

    // --- creating for a brand-new owner -----------------------------------

    public function test_it_creates_the_listing_and_the_owner_account(): void
    {
        Mail::fake();
        $staff = $this->staff();

        $this->actingAs($staff)
            ->post(route('admin.properties.store'), $this->payload())
            ->assertRedirect(route('admin.properties.index'));

        $owner = User::where('email', 'marguerite@example.com')->sole();
        $property = Property::sole();

        $this->assertSame($owner->id, $property->host_id);
        $this->assertSame('Cliffside Cottage', $property->title);
        $this->assertSame('US', $property->country, 'The country code is not upper-cased.');
        $this->assertSame($staff->id, $owner->created_by_user_id);
    }

    /**
     * Dollars in, integer cents stored. 249.99 * 100 in binary floating point
     * is 24998.999…, so a bare cast truncates to 24998 — a cent short on every
     * listing priced with a 99.
     */
    public function test_the_nightly_rate_converts_to_exact_cents(): void
    {
        Mail::fake();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.store'), $this->payload(['price_dollars' => '249.99']));

        $this->assertSame(24999, Property::sole()->price_cents);
    }

    /** The staff-issued password must not survive the owner's first sign-in. */
    public function test_the_new_owner_must_change_the_issued_password(): void
    {
        Mail::fake();

        $this->actingAs($this->staff())->post(route('admin.properties.store'), $this->payload());

        $owner = User::where('email', 'marguerite@example.com')->sole();

        $this->assertTrue($owner->must_change_password);

        $this->actingAs($owner)->get('/dashboard')
            ->assertRedirect(route('password.first-change'));
    }

    public function test_the_one_time_password_is_shown_to_staff_once(): void
    {
        Mail::fake();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.store'), $this->payload())
            ->assertSessionHas('owner_credentials');

        $creds = session('owner_credentials');
        $owner = User::where('email', 'marguerite@example.com')->sole();

        $this->assertTrue(Hash::check($creds['password'], $owner->password));
    }

    // --- creating for an existing owner -----------------------------------

    public function test_it_can_create_a_listing_for_an_existing_account(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['email' => 'already@example.com']);

        $this->actingAs($this->staff())->post(route('admin.properties.store'), $this->payload([
            'owner_mode' => 'existing',
            'host_id'    => $owner->id,
        ]));

        $this->assertSame($owner->id, Property::sole()->host_id);
        $this->assertSame(1, User::where('email', 'already@example.com')->count());
    }

    /**
     * Staff choosing "new" for an email that already has an account should not
     * blow up — it is the ordinary case of them not knowing. Reuse it.
     */
    public function test_a_duplicate_email_reuses_the_existing_account(): void
    {
        Mail::fake();
        $existing = User::factory()->create(['email' => 'marguerite@example.com']);

        $this->actingAs($this->staff())->post(route('admin.properties.store'), $this->payload());

        $this->assertSame(1, User::where('email', 'marguerite@example.com')->count());
        $this->assertSame($existing->id, Property::sole()->host_id);
    }

    // --- the owner is told -------------------------------------------------

    public function test_the_owner_is_emailed(): void
    {
        Mail::fake();
        config([
            'mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.com',
            'mail.mailers.smtp.username' => 'u', 'mail.mailers.smtp.password' => 'p',
        ]);

        $this->actingAs($this->staff())->post(route('admin.properties.store'), $this->payload());

        Mail::assertSent(ListingCreatedForOwner::class,
            fn ($mail) => $mail->hasTo('marguerite@example.com'));
    }

    public function test_the_notification_can_be_suppressed_deliberately(): void
    {
        Mail::fake();
        config([
            'mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.com',
            'mail.mailers.smtp.username' => 'u', 'mail.mailers.smtp.password' => 'p',
        ]);

        $this->actingAs($this->staff())
            ->post(route('admin.properties.store'), $this->payload(['notify_owner' => 0]));

        Mail::assertNotSent(ListingCreatedForOwner::class);
    }

    /**
     * Mail is undeliverable in production today, so this is the live path: the
     * listing and the account must still be created, and staff must be told
     * the email did not go.
     */
    public function test_a_mail_outage_does_not_lose_the_listing(): void
    {
        // Seed BEFORE switching the environment: db:seed asks for confirmation
        // once the app thinks it is in production, and a prompt in a test run
        // is an error, not a question anyone can answer.
        $staff = $this->staff();

        config(['mail.default' => 'log']);
        app()->detectEnvironment(fn () => 'production');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->actingAs($staff)
            ->post(route('admin.properties.store'), $this->payload())
            ->assertRedirect(route('admin.properties.index'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'could NOT be emailed'));

        $this->assertSame(1, Property::count());
        $this->assertSame(1, User::where('email', 'marguerite@example.com')->count());
    }

    // --- validation --------------------------------------------------------

    public function test_an_owner_must_be_identified(): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.properties.store'), $this->payload([
                'owner_mode' => 'new', 'owner_email' => null,
            ]))
            ->assertSessionHasErrors('owner_email');

        $this->assertSame(0, Property::count());
    }

    public function test_a_title_and_rate_are_required(): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.properties.store'), $this->payload([
                'title' => '', 'price_dollars' => '',
            ]))
            ->assertSessionHasErrors(['title', 'price_dollars']);
    }

    /** Nothing is written if any part fails — no orphan account. */
    public function test_nothing_is_created_when_validation_fails(): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.properties.store'), $this->payload(['title' => '']));

        $this->assertSame(0, Property::count());
        $this->assertSame(0, User::where('email', 'marguerite@example.com')->count());
    }
}
