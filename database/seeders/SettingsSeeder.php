<?php

namespace Database\Seeders;

use App\Models\CancellationPolicyConfig;
use App\Models\EmailTemplate;
use App\Models\FeatureFlag;
use App\Models\FeeSchedule;
use App\Models\PaymentProcessorConfig;
use App\Models\PropertyType;
use App\Models\Setting;
use App\Models\TaxRule;
use App\Models\VacationClubProgram;
use App\Services\Settings\SettingsSchema;
use Illuminate\Database\Seeder;

/**
 * Seeds the Settings & Configuration subsystem. IDEMPOTENT and
 * production-safe: rows are matched by key/code and only INSERTED when
 * missing — an operator-changed value is never overwritten on re-run,
 * and nothing is ever deleted.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSettings();
        $this->seedFeatureFlags();
        $this->seedPaymentProcessors();
        $this->seedCancellationPolicies();
        $this->seedFeeSchedule();
        $this->seedTaxRules();
        $this->seedVacationClubPrograms();
        $this->seedEmailTemplates();
        $this->seedPropertyTypes();
    }

    /** Every catalog key at its default; existing rows untouched. */
    private function seedSettings(): void
    {
        $existing = Setting::query()->pluck('key')->all();

        foreach (SettingsSchema::catalog() as $key => $spec) {
            if (in_array($key, $existing, true)) {
                continue;
            }

            // Encrypted defaults are all empty strings — store plaintext ''
            // (nothing secret to protect); operator writes get encrypted.
            $storageType = $spec['type'] === 'encrypted' ? 'string' : $spec['type'];

            Setting::create([
                'group' => $spec['group'],
                'key' => $key,
                'type' => $spec['type'],
                'value' => null, // unset -> reads fall through to default
                'default_value' => Setting::castToStorage($spec['default'], $storageType),
                'enum_options' => $spec['options'],
                'is_public' => $spec['public'],
                'is_sensitive' => $spec['sensitive'],
                'label' => $spec['label'],
                'help_text' => $spec['help'],
                'sort_order' => $spec['sort'],
            ]);
        }
    }

    private function seedFeatureFlags(): void
    {
        foreach (SettingsSchema::FEATURE_FLAGS as $key => $description) {
            FeatureFlag::query()->firstOrCreate(
                ['key' => $key],
                [
                    'enabled' => ! in_array($key, SettingsSchema::FLAGS_DEFAULT_OFF, true),
                    'scope' => 'global',
                    'description' => $description,
                ],
            );
        }
    }

    /**
     * One row per processor in App\Enums\PaymentProcessor (except the
     * deprecated Stripe case — historical rows only, no new charges).
     * NMI is the live default; everything else starts disabled in test mode.
     */
    private function seedPaymentProcessors(): void
    {
        $processors = [
            ['nmi', 'NMI', true, 'live', 10, true],
            ['authorizenet', 'Authorize.Net', false, 'test', 20, false],
            ['nuvei', 'Nuvei', false, 'test', 30, false],
            ['mes', 'Merchant e-Solutions', false, 'test', 40, false],
            ['paymentcloud', 'PaymentCloud', false, 'test', 50, false],
            ['ems', 'EMS', false, 'test', 60, false],
            ['nexio', 'Nexio', false, 'test', 70, false],
            ['netevia', 'Netevia', false, 'test', 80, false],
            ['kurv', 'Kurv', false, 'test', 90, false],
        ];

        foreach ($processors as [$code, $name, $enabled, $mode, $priority, $isDefault]) {
            PaymentProcessorConfig::query()->firstOrCreate(
                ['code' => $code],
                [
                    'display_name' => $name,
                    'enabled' => $enabled,
                    'mode' => $mode,
                    'priority' => $priority,
                    'is_default' => $isDefault,
                    'currencies' => ['USD'],
                ],
            );
        }
    }

    /**
     * Tiers reproduce the previously hardcoded RefundCalculator math exactly
     * (FR-3.4), so seeding changes nothing until an operator edits them:
     *   flexible: 100% >= 1 day out; moderate: 100% >= 5 days, 50% >= 1 day;
     *   strict: 50% >= 7 days; non_refundable: never.
     */
    private function seedCancellationPolicies(): void
    {
        $policies = [
            ['flexible', 'Flexible', 'Full refund up to 24 hours before check-in.', [
                ['days_before' => 1, 'refund_pct' => 100],
            ], false],
            ['moderate', 'Moderate', 'Full refund up to 5 days before check-in; 50% up to 24 hours before.', [
                ['days_before' => 5, 'refund_pct' => 100],
                ['days_before' => 1, 'refund_pct' => 50],
            ], true],
            ['strict', 'Strict', '50% refund up to 7 days before check-in; none after.', [
                ['days_before' => 7, 'refund_pct' => 50],
            ], false],
            ['non_refundable', 'Non-refundable', 'No refund on traveler-initiated cancellation.', [], false],
        ];

        foreach ($policies as [$code, $name, $description, $tiers, $isDefault]) {
            CancellationPolicyConfig::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $description,
                    'refund_tiers' => $tiers,
                    'is_default' => $isDefault,
                    'active' => true,
                ],
            );
        }
    }

    private function seedFeeSchedule(): void
    {
        FeeSchedule::query()->firstOrCreate(
            ['name' => 'Standard'],
            [
                'guest_service_pct' => 12,
                'host_commission_pct' => 3,
                'cleaning_fee_mode' => 'host_set',
                'security_deposit_mode' => 'none',
                'applies_to' => 'all',
                'active' => true,
            ],
        );
    }

    /** The previously hardcoded 8% booking tax, now editable. */
    private function seedTaxRules(): void
    {
        TaxRule::query()->firstOrCreate(
            ['name' => 'US default lodging tax'],
            [
                'jurisdiction' => 'United States (default)',
                'country_code' => 'US',
                'rate_bps' => 800,
                'applies_to' => 'booking_total',
                'active' => true,
            ],
        );
    }

    private function seedVacationClubPrograms(): void
    {
        $programs = [
            ['marriott_vacation_club', 'Marriott Vacation Club', true],
            ['hilton_grand_vacations', 'Hilton Grand Vacations', true],
            ['disney_vacation_club', 'Disney Vacation Club', true],
            ['rci', 'RCI', true],
            ['interval_international', 'Interval International', true],
        ];

        foreach ($programs as $i => [$code, $name, $pointsBased]) {
            VacationClubProgram::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'points_based' => $pointsBased,
                    'active' => true,
                    'sort_order' => ($i + 1) * 10,
                ],
            );
        }
    }

    private function seedEmailTemplates(): void
    {
        $templates = [
            [
                'key' => 'booking_confirmation',
                'name' => 'Booking confirmation',
                'subject' => 'Your {{site_name}} booking is confirmed — {{confirmation_code}}',
                'body_markdown' => "Hi {{guest_name}},\n\nYour stay at **{{property_title}}** is confirmed.\n\n- Check-in: {{check_in_date}} from {{checkin_time}}\n- Check-out: {{check_out_date}} by {{checkout_time}}\n- Confirmation code: **{{confirmation_code}}**\n- Total paid: {{total_amount}}\n\nSee your trip details any time at {{booking_url}}.\n\nSafe travels,\nThe {{site_name}} team",
                'variables' => ['guest_name', 'property_title', 'check_in_date', 'check_out_date', 'checkin_time', 'checkout_time', 'confirmation_code', 'total_amount', 'booking_url', 'site_name'],
            ],
            [
                'key' => 'mlp_auto_response',
                'name' => 'Managed Listing enquiry auto-response',
                'subject' => 'We received your enquiry — {{site_name}} Managed Listing Program',
                'body_markdown' => "Hi {{name}},\n\nThanks for your interest in the Managed Listing Program. A member specialist will reach out within {{sla_hours}} business hours to talk through how your {{program}} membership can start working for you.\n\nIn the meantime, feel free to reply to this email with any questions.\n\n— The {{site_name}} member team",
                'variables' => ['name', 'program', 'sla_hours', 'site_name'],
            ],
            [
                'key' => 'password_reset',
                'name' => 'Password reset',
                'subject' => 'Reset your {{site_name}} password',
                'body_markdown' => "Hi {{name}},\n\nWe received a request to reset your password. This link expires in {{ttl_minutes}} minutes:\n\n{{reset_url}}\n\nIf you didn't request this, you can safely ignore this email.\n\n— {{site_name}}",
                'variables' => ['name', 'reset_url', 'ttl_minutes', 'site_name'],
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::query()->firstOrCreate(
                ['key' => $template['key'], 'version' => 1],
                [
                    'name' => $template['name'],
                    'subject' => $template['subject'],
                    'body_markdown' => $template['body_markdown'],
                    'variables' => $template['variables'],
                    'active' => true,
                ],
            );
        }
    }

    private function seedPropertyTypes(): void
    {
        $types = [
            ['house', 'House'],
            ['apartment', 'Apartment'],
            ['condo', 'Condo'],
            ['villa', 'Villa'],
            ['cabin', 'Cabin'],
            ['townhome', 'Townhome'],
            ['resort_residence', 'Resort residence'],
            ['boat', 'Boat'],
            ['unique_stay', 'Unique stay'],
        ];

        foreach ($types as $i => [$slug, $label]) {
            PropertyType::query()->firstOrCreate(
                ['slug' => $slug],
                ['label' => $label, 'active' => true, 'sort_order' => ($i + 1) * 10],
            );
        }
    }
}
