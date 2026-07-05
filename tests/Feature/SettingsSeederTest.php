<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CancellationPolicyConfig;
use App\Models\EmailTemplate;
use App\Models\FeatureFlag;
use App\Models\PaymentProcessorConfig;
use App\Models\Setting;
use App\Models\User;
use App\Models\VacationClubProgram;
use App\Services\Settings\SettingsRepository;
use App\Services\Settings\SettingsSchema;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_idempotent(): void
    {
        (new SettingsSeeder)->run();

        $counts = [
            Setting::count(),
            FeatureFlag::count(),
            PaymentProcessorConfig::count(),
            CancellationPolicyConfig::count(),
            VacationClubProgram::count(),
            EmailTemplate::count(),
        ];

        (new SettingsSeeder)->run();

        $this->assertSame($counts, [
            Setting::count(),
            FeatureFlag::count(),
            PaymentProcessorConfig::count(),
            CancellationPolicyConfig::count(),
            VacationClubProgram::count(),
            EmailTemplate::count(),
        ]);
    }

    public function test_seeder_covers_every_catalog_key(): void
    {
        (new SettingsSeeder)->run();

        $this->assertSame(count(SettingsSchema::catalog()), Setting::count());
    }

    public function test_reseed_never_clobbers_operator_values(): void
    {
        (new SettingsSeeder)->run();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        app(SettingsRepository::class)->set('fees.guest_service_pct', 25, $admin);

        (new SettingsSeeder)->run();

        $this->assertSame(25, setting('fees.guest_service_pct'));
    }

    public function test_seeded_defaults_match_current_behavior(): void
    {
        (new SettingsSeeder)->run();

        // NMI live + default; the other processors parked disabled in test mode.
        $nmi = PaymentProcessorConfig::query()->where('code', 'nmi')->sole();
        $this->assertTrue($nmi->enabled && $nmi->is_default && $nmi->mode === 'live');
        $this->assertSame(9, PaymentProcessorConfig::count());
        $this->assertSame(1, PaymentProcessorConfig::query()->where('enabled', true)->count());

        // SMS flag off, everything else on by default.
        $this->assertFalse(FeatureFlag::query()->where('key', 'sms_notifications')->sole()->enabled);
        $this->assertTrue(FeatureFlag::query()->where('key', 'ai_chat')->sole()->enabled);

        // Cancellation tiers reproduce the legacy RefundCalculator math.
        $moderate = CancellationPolicyConfig::query()->where('code', 'moderate')->sole();
        $this->assertSame([
            ['days_before' => 5, 'refund_pct' => 100],
            ['days_before' => 1, 'refund_pct' => 50],
        ], $moderate->refund_tiers);
        $this->assertTrue($moderate->is_default);
    }

    public function test_no_banned_terminology_in_seeded_copy(): void
    {
        (new SettingsSeeder)->run();

        // FR-9.8: the T-word is banned in user-facing copy — check every
        // seeded default, label, template body, and program name.
        $haystack = strtolower(implode(' ', [
            Setting::query()->pluck('default_value')->implode(' '),
            Setting::query()->pluck('label')->implode(' '),
            Setting::query()->pluck('help_text')->implode(' '),
            EmailTemplate::query()->pluck('body_markdown')->implode(' '),
            EmailTemplate::query()->pluck('subject')->implode(' '),
            VacationClubProgram::query()->pluck('name')->implode(' '),
            FeatureFlag::query()->pluck('description')->implode(' '),
        ]));

        $this->assertStringNotContainsString('timeshare', $haystack);
    }
}
