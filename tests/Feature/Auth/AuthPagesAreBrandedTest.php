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

    /**
     * The profile page is signed-in-only, so an anonymous crawl reports it as
     * a 302 and never sees that it was rendering the Laravel application logo
     * to every logged-in customer. It is checked here explicitly for that
     * reason.
     */
    public function test_profile_page_carries_no_laravel_branding(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/profile')->assertOk()->getContent();

        $this->assertStringNotContainsString('application-logo', $html);
        $this->assertStringNotContainsString('x-app-layout', $html);
        $this->assertStringContainsString('Vaytoven', $html);
    }

    public function test_no_page_title_falls_back_to_the_word_laravel(): void
    {
        $user = User::factory()->create();

        foreach (['/', '/properties', '/help', '/login', '/register'] as $path) {
            $this->assertStringNotContainsString(
                '<title>Laravel',
                $this->get($path)->getContent(),
                "{$path} renders the default Laravel title",
            );
        }

        $this->assertStringNotContainsString(
            '<title>Laravel',
            $this->actingAs($user)->get('/profile')->getContent(),
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
