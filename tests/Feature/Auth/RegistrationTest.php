<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        // Phase 13: registration now requires explicit ToS + Privacy
        // acceptance. Materialise the current versions first so the controller
        // can record TermsAcceptance rows for them.
        app(\App\Services\Legal\LegalDocumentRegistry::class)->materialiseAll();

        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'phone' => '+1 (555) 010-2030',
            'password' => 'password',
            'password_confirmation' => 'password',
            'accept_terms' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        // `name` stays the authoritative display value and is composed from
        // the parts, so every existing read site keeps working unchanged.
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '+1 (555) 010-2030',
        ]);
    }

    public function test_registration_requires_the_new_fields(): void
    {
        $this->post('/register', [
            'email' => 'nope@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'accept_terms' => '1',
        ])->assertSessionHasErrors(['first_name', 'last_name', 'phone']);

        $this->assertGuest();
    }

    public function test_registration_rejects_a_phone_that_is_not_a_phone(): void
    {
        $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'phone' => 'call me maybe',
            'password' => 'password',
            'password_confirmation' => 'password',
            'accept_terms' => '1',
        ])->assertSessionHasErrors('phone');
    }
}
