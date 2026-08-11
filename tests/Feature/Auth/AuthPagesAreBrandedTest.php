<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Every customer-facing auth screen must be Vaytoven-branded, not a Breeze
 * default. These assertions are deliberately structural rather than visual:
 * a page that still renders the Breeze guest layout pulls in Tailwind utility
 * markup and the Laravel application logo, and neither can survive a redesign
 * that actually happened.
 *
 * The guard matters because `php artisan breeze:install` or a scaffold
 * regeneration silently restores those views, and nobody reviews a diff that
 * only touches resources/views/auth.
 */
class AuthPagesAreBrandedTest extends TestCase
{
    use RefreshDatabase;

    /** Markup that only appears if a page is still on the Breeze scaffold. */
    private const BREEZE_TELLS = [
        'x-guest-layout',
        'application-logo',
        // Breeze's default button/link utility soup.
        'focus:ring-indigo-500',
        'text-gray-600',
    ];

    private function assertBranded(string $html, string $page): void
    {
        foreach (self::BREEZE_TELLS as $tell) {
            $this->assertStringNotContainsString($tell, $html, "{$page} still carries Breeze markup: {$tell}");
        }

        // Positive signals: the brand layout's own hooks.
        $this->assertStringContainsString('vyt-auth-card', $html, "{$page} is not on the Vaytoven auth layout");
        $this->assertStringContainsString('Fraunces', $html, "{$page} is missing the brand typography");
        $this->assertStringContainsString('vyt-auth-grad', $html, "{$page} is missing the Vaytoven logo");
        // Responsive.
        $this->assertStringContainsString('width=device-width', $html, "{$page} is not responsive");
    }

    public function test_register_page_is_branded(): void
    {
        $this->assertBranded($this->get('/register')->assertOk()->getContent(), 'Register');
    }

    public function test_login_page_is_branded(): void
    {
        $this->assertBranded($this->get('/login')->assertOk()->getContent(), 'Login');
    }

    public function test_forgot_password_page_is_branded(): void
    {
        $this->assertBranded($this->get('/forgot-password')->assertOk()->getContent(), 'Forgot password');
    }

    public function test_reset_password_page_is_branded(): void
    {
        $token = Password::createToken(User::factory()->create());

        $this->assertBranded($this->get("/reset-password/{$token}")->assertOk()->getContent(), 'Reset password');
    }

    public function test_verify_email_page_is_branded(): void
    {
        $user = User::factory()->unverified()->create();

        $this->assertBranded(
            $this->actingAs($user)->get('/verify-email')->assertOk()->getContent(),
            'Verify email',
        );
    }

    public function test_confirm_password_page_is_branded(): void
    {
        $user = User::factory()->create();

        $this->assertBranded(
            $this->actingAs($user)->get('/confirm-password')->assertOk()->getContent(),
            'Confirm password',
        );
    }

    public function test_register_page_shows_the_required_fields_and_cta(): void
    {
        $html = $this->get('/register')->assertOk()->getContent();

        foreach (['first_name', 'last_name', 'email', 'phone', 'password', 'password_confirmation'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html, "Register is missing the {$field} field");
        }

        $this->assertStringContainsString('Create account', $html);
        $this->assertStringContainsString('Already have an account?', $html);
    }
}
