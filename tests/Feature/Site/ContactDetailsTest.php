<?php

namespace Tests\Feature\Site;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One real inbox, printed where people look for it.
 *
 * The site used to advertise five different mailboxes — support@, press@,
 * privacy@, specialist@ and hello@ — none of which exist. Each one was a dead
 * end printed on a public page, and the privacy policy pointed a GDPR/CCPA data
 * request at one of them, which is the worst place to lose a message.
 *
 * contact@vaytoven.com is the address the company actually monitors.
 */
class ContactDetailsTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'contact@vaytoven.com';

    /** Mailboxes that were invented per-surface and deliver nowhere. */
    private const DEAD_MAILBOXES = [
        'support@vaytoven.com',
        'press@vaytoven.com',
        'privacy@vaytoven.com',
        'specialist@vaytoven.com',
    ];

    /**
     * The legal documents must carry it: they are the binding text, and a
     * notice provision with no address to send notices to is not a provision.
     */
    public function test_every_legal_document_publishes_the_contact_email(): void
    {
        foreach (['/legal/tos', '/legal/privacy', '/legal/member-agreement'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('mailto:'.self::EMAIL, $html,
                "{$url} does not publish a contact email.");
        }
    }

    /** Every public page, including the two that had no footer at all. */
    private const PUBLIC_PAGES = [
        '/', '/contact', '/about', '/press', '/become-a-host', '/members',
        '/earnings-calculator', '/host-resources', '/destinations', '/careers',
    ];

    /** The footer is on every public page, so this is the site-wide answer. */
    public function test_the_shared_footer_publishes_the_contact_email_and_phone(): void
    {
        foreach (self::PUBLIC_PAGES as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('mailto:'.self::EMAIL, $html,
                "{$url} has no contact email in the footer.");
            $this->assertStringContainsString('tel:+18777829868', $html,
                "{$url} has no contact phone in the footer.");
        }
    }

    /**
     * Markup without CSS is not a footer.
     *
     * The footer was extracted into a shared partial but its styles were left
     * inline in welcome.blade.php, so every page rendered through layouts/site
     * shipped the markup unstyled — the homepage looked right, which is why it
     * survived. An address nobody can read is not published.
     */
    public function test_every_page_that_renders_the_footer_also_ships_its_styles(): void
    {
        foreach (self::PUBLIC_PAGES as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('footer-grid', $html,
                "{$url} does not render the shared footer.");
            $this->assertStringContainsString('.footer-grid {', $html,
                "{$url} renders the footer markup but ships no footer CSS.");
        }
    }

    /**
     * No page may advertise a mailbox that bounces.
     *
     * Checked across the public surfaces AND the member dashboard, because the
     * dashboard's "Contact your member specialist" link was the least visible
     * of the dead addresses and therefore the longest-lived.
     */
    public function test_no_page_advertises_a_mailbox_that_does_not_exist(): void
    {
        $pages = [
            '/' => $this->get('/')->getContent(),
            '/contact' => $this->get('/contact')->getContent(),
            '/press' => $this->get('/press')->getContent(),
            '/legal/tos' => $this->get('/legal/tos')->getContent(),
            '/legal/privacy' => $this->get('/legal/privacy')->getContent(),
            '/legal/member-agreement' => $this->get('/legal/member-agreement')->getContent(),
        ];

        $member = User::factory()->create(['role' => \App\Enums\UserRole::Member]);
        $pages['/dashboard'] = $this->actingAs($member)->get('/dashboard')->getContent();

        foreach ($pages as $url => $html) {
            foreach (self::DEAD_MAILBOXES as $dead) {
                $this->assertStringNotContainsString($dead, $html,
                    "{$url} still links {$dead}, which delivers nowhere.");
            }
        }
    }

    /**
     * The support-chat fallback is the one message a visitor sees when the
     * assistant is down, so it must not hardcode a stale address.
     */
    public function test_the_support_chat_fallback_uses_the_configured_inbox(): void
    {
        $spec = \App\Services\Settings\SettingsSchema::spec('ai_chat.enabled');

        \App\Models\Setting::query()->updateOrCreate(
            ['key' => 'ai_chat.enabled'],
            [
                'group' => $spec['group'],
                'type' => $spec['type'],
                'label' => $spec['label'],
                'value' => '0',
                'default_value' => '1',
                'is_public' => false,
                'is_sensitive' => false,
            ],
        );
        app(\App\Services\Settings\SettingsRepository::class)->forget('ai_chat.enabled');

        $reply = $this->postJson('/api/v1/support/chat', ['message' => 'hello'])
            ->assertStatus(503)
            ->json('reply');

        $this->assertStringContainsString(self::EMAIL, $reply);
    }
}
