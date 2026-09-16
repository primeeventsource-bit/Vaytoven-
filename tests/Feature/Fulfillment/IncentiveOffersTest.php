<?php

namespace Tests\Feature\Fulfillment;

use App\Enums\UserRole;
use App\Models\AdvertisementFulfillmentRecord as Record;
use App\Models\Role;
use App\Models\User;
use App\Services\Fulfillment\IncentiveCatalog;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Staff choose which enrollment thank-you each client is sent: a default for
 * new clients, or a specific offer on a client's profile. Once a client has
 * seen an offer, what they saw is fixed on the record.
 */
class IncentiveOffersTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RbacSeeder::class);
        $user = User::factory()->create(['role' => UserRole::SuperAdmin, 'must_change_password' => false]);
        $user->roles()->sync([Role::where('key', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    private function client(string $email): User
    {
        return User::factory()->create(['role' => UserRole::Member, 'email' => $email, 'password' => Hash::make('secret-pass-9')]);
    }

    private function signInAndSeeOffer(User $client): \Illuminate\Testing\TestResponse
    {
        $this->post('/logout');
        $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => $client->email, 'password' => 'secret-pass-9'])->assertRedirect();

        return $this->get(route('member.incentive.show'))->assertOk();
    }

    public function test_the_catalog_has_all_four_offers_and_no_forbidden_wording(): void
    {
        $this->assertSame(['dining-rewards-300', 'airfare-hotel-2x2', 'cruise-4-night', 'hotel-savings-400', 'hotel-savings-500'], IncentiveCatalog::keys());

        foreach (IncentiveCatalog::all() as $offer) {
            $text = strtolower(implode(' ', [$offer->name, $offer->headline, $offer->subheadline, $offer->tagline, $offer->finePrint, ...$offer->included]));
            $this->assertStringNotContainsString('timeshare', $text, "{$offer->key} uses forbidden wording");
        }
    }

    public function test_the_default_offer_is_what_new_clients_see_and_record(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->get(route('admin.incentives.index'))
            ->assertOk()
            ->assertSee('2 Airfares')->assertSee('4-Night Luxury Cruise')->assertSee('$400')->assertSee('$500')->assertSee('in Dining Rewards');

        $this->actingAs($staff)->post(route('admin.incentives.default'), ['incentive_key' => 'hotel-savings-500'])
            ->assertRedirect(route('admin.incentives.index'));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'setting.update']);
        $this->assertSame('hotel-savings-500', IncentiveCatalog::defaultKey());

        $client = $this->client('new.client@example.com');
        $this->signInAndSeeOffer($client)
            ->assertSee('$500')->assertSee('in Hotel Savings')->assertSee('VIEW MY HOTEL REWARD');

        $presented = Record::where('event', Record::EVENT_INCENTIVE_PRESENTED)->sole();
        $this->assertSame('hotel-savings-500', $presented->incentive_key);
        $this->assertSame('$500 Hotel Savings', $presented->incentive_name);
        $this->assertSame(IncentiveCatalog::find('hotel-savings-500')->presentationHash(), $presented->incentive_presentation_hash);
    }

    public function test_a_client_can_be_given_a_different_offer_until_they_have_seen_it(): void
    {
        $staff  = $this->staff();
        $client = $this->client('picked@example.com');

        $this->actingAs($staff)->post(route('admin.members.incentive-assign', $client), ['incentive_key' => 'airfare-hotel-2x2'])
            ->assertRedirect();
        $this->assertSame('airfare-hotel-2x2', $client->refresh()->incentive_key);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'member.incentive.assigned']);

        // Other clients still get the default.
        $this->assertSame(IncentiveCatalog::DEFAULT_KEY, IncentiveCatalog::forMember($this->client('other@example.com'))->key);

        $this->signInAndSeeOffer($client)
            ->assertSee('2 Airfares')->assertSee('No sales presentations. No tours. Confirmed in writing.');

        $this->post(route('member.incentive.acknowledge'), ['choice' => 'view'])->assertRedirect(route('member.incentive.reward'));
        $this->assertSame('VIEW MY TRAVEL REWARD', Record::where('event', Record::EVENT_INCENTIVE_ACKNOWLEDGED)->sole()->metadata['button']);

        // Seen: the offer is locked.
        $this->post('/logout');
        $this->app['auth']->forgetGuards();
        $this->actingAs($staff)->post(route('admin.members.incentive-assign', $client), ['incentive_key' => 'hotel-savings-400'])
            ->assertSessionHasErrors('incentive_key');
        $this->assertSame('airfare-hotel-2x2', $client->refresh()->incentive_key);

        $this->actingAs($staff)->get(route('admin.members.show', ['user' => $client, 'tab' => 'advertising']))
            ->assertOk()->assertSee('2 Airfares + 2 Nights Hotel')->assertSee('Presented — fixed on the record');
    }

    public function test_the_cruise_offer_can_be_sent_and_is_recorded(): void
    {
        $staff  = $this->staff();
        $client = $this->client('cruise.client@example.com');

        $this->actingAs($staff)->get(route('admin.incentives.index'))->assertOk()->assertSee('4-Night Luxury Cruise');
        $this->actingAs($staff)->post(route('admin.members.incentive-assign', $client), ['incentive_key' => 'cruise-4-night'])->assertRedirect();

        $this->signInAndSeeOffer($client)
            ->assertSee('4-Night Luxury Cruise')
            ->assertSee('5 days, 4 nights at sea')
            ->assertSee('Interior stateroom accommodations for two guests')
            ->assertSee('VIEW MY CRUISE REWARD');

        $this->assertSame('4-Night Luxury Cruise', Record::where('event', Record::EVENT_INCENTIVE_PRESENTED)->sole()->incentive_name);
    }

    public function test_an_unknown_offer_is_refused(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('admin.incentives.default'), ['incentive_key' => 'cruise-7-nights'])
            ->assertSessionHasErrors('incentive_key');
        $this->assertSame(IncentiveCatalog::DEFAULT_KEY, IncentiveCatalog::defaultKey());
    }
}
