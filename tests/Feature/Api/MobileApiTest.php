<?php

namespace Tests\Feature\Api;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Services\Legal\LegalDocumentRegistry;
use Database\Seeders\LegalDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_account_requires_authentication(): void
    {
        $this->getJson('/api/v1/mobile/account')->assertUnauthorized();
    }

    public function test_deactivated_account_cannot_use_an_existing_token(): void
    {
        $user = User::factory()->create(['deactivated_at' => now()]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/mobile/account')->assertForbidden();
        $this->getJson('/api/v1/mobile/saved')->assertForbidden();
    }

    public function test_property_api_preserves_the_websites_sale_and_rental_price_labels(): void
    {
        $property = Property::factory()->create(['status' => PropertyStatus::Active, 'listing_type' => 'sale']);
        $this->getJson("/api/v1/properties/{$property->id}")->assertOk()
            ->assertJsonPath('data.listing_type', 'sale')->assertJsonPath('data.price_caption', 'Asking price');
        $property->update(['listing_type' => 'rent']);
        $this->getJson("/api/v1/properties/{$property->id}")->assertOk()
            ->assertJsonPath('data.price_caption', '7 days / 6 nights');
    }

    public function test_saves_are_idempotent_and_private_to_the_account(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->create(['status' => PropertyStatus::Active]);
        Sanctum::actingAs($user);
        $this->putJson("/api/v1/mobile/saved/{$property->id}")->assertOk();
        $this->putJson("/api/v1/mobile/saved/{$property->id}")->assertOk();
        $this->getJson('/api/v1/mobile/saved')->assertJsonCount(1, 'data');
        $this->assertDatabaseCount('wishlist_properties', 1);
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/mobile/saved')->assertJsonCount(0, 'data');
        Sanctum::actingAs($user);
        $this->deleteJson("/api/v1/mobile/saved/{$property->id}")->assertOk();
        $this->getJson('/api/v1/mobile/saved')->assertJsonCount(0, 'data');
    }

    public function test_an_unpublished_property_cannot_be_saved_or_receive_an_offer(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $property = Property::factory()->create(['status' => PropertyStatus::Draft]);
        $this->putJson("/api/v1/mobile/saved/{$property->id}")->assertNotFound();
        $this->postJson("/api/v1/mobile/properties/{$property->id}/offers", ['kind' => 'inquiry'])->assertNotFound();
    }

    public function test_offer_uses_integer_cents_and_is_visible_only_to_buyer_and_owner(): void
    {
        $buyer = User::factory()->create();
        $property = Property::factory()->create(['status' => PropertyStatus::Active]);
        Sanctum::actingAs($buyer);
        $this->postJson("/api/v1/mobile/properties/{$property->id}/offers", [
            'kind' => 'offer', 'amount_cents' => 12345, 'message' => 'Is this available?',
        ])->assertCreated()->assertJsonPath('data.amount_cents', 12345);
        $this->getJson('/api/v1/mobile/offers')->assertJsonCount(1, 'data');
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/mobile/offers')->assertJsonCount(0, 'data');
        Sanctum::actingAs($property->host);
        $this->getJson('/api/v1/mobile/offers')->assertJsonCount(1, 'data');
    }

    public function test_fractional_cents_and_own_listing_offers_are_rejected(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->create(['status' => PropertyStatus::Active, 'host_id' => $user->id]);
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/mobile/properties/{$property->id}/offers", ['kind' => 'offer', 'amount_cents' => 123.45])->assertUnprocessable();
        $this->postJson("/api/v1/mobile/properties/{$property->id}/offers", ['kind' => 'inquiry'])->assertForbidden();
    }

    public function test_only_owner_can_respond_and_offer_cannot_be_responded_to_twice(): void
    {
        $buyer = User::factory()->create();
        $property = Property::factory()->create(['status' => PropertyStatus::Active]);
        Sanctum::actingAs($buyer);
        $id = $this->postJson("/api/v1/mobile/properties/{$property->id}/offers", ['kind' => 'inquiry'])->json('data.id');
        $this->postJson("/api/v1/mobile/offers/{$id}/respond", ['decision' => 'accepted'])->assertForbidden();
        Sanctum::actingAs($property->host);
        $this->postJson("/api/v1/mobile/offers/{$id}/respond", ['decision' => 'accepted'])->assertOk();
        $this->postJson("/api/v1/mobile/offers/{$id}/respond", ['decision' => 'declined'])->assertUnprocessable();
    }

    public function test_terms_gate_returns_json_and_accepts_only_the_reviewed_versions(): void
    {
        $this->seed(LegalDocumentSeeder::class);
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/mobile/saved')->assertStatus(409)->assertJsonPath('error', 'terms_required');
        $ids = array_map(fn ($v) => $v->id, app(LegalDocumentRegistry::class)->registrationRequired());
        $this->postJson('/api/v1/mobile/terms', ['accept' => true, 'version_ids' => []])->assertUnprocessable();
        $this->postJson('/api/v1/mobile/terms', ['accept' => true, 'version_ids' => $ids])->assertOk();
        $this->assertEquals(count($ids), TermsAcceptance::where('user_id', $user->id)->count());
        $this->getJson('/api/v1/mobile/saved')->assertOk();
    }

    public function test_temporary_password_account_can_change_password_and_revokes_other_tokens(): void
    {
        $user = User::factory()->create(['password' => 'TemporaryPass123', 'must_change_password' => true]);
        $current = $user->createToken('current');
        $user->createToken('other-device');
        $this->withToken($current->plainTextToken);
        $this->getJson('/api/v1/mobile/saved')->assertStatus(423);
        $this->postJson('/api/v1/mobile/password', [
            'current_password' => 'TemporaryPass123',
            'password' => 'MyNewPassword456', 'password_confirmation' => 'MyNewPassword456',
        ])->assertOk();
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
