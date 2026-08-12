<?php

namespace Tests\Concerns;

use App\Models\Setting;
use App\Services\Settings\SettingsRepository;
use App\Services\Settings\SettingsSchema;

/**
 * The guest stay checkout is off by default — Vaytoven advertises listings and
 * does not collect rental funds or charge visitors for stays. The code is
 * retained behind `booking.stay_checkout_enabled` for a future managed-booking
 * arrangement, and the tests that cover it opt in explicitly.
 *
 * Using this trait is therefore a statement: "this test exercises the gated
 * checkout, not the default product."
 */
trait EnablesStayCheckout
{
    protected function enableStayCheckout(): void
    {
        // Built from the schema spec rather than hand-listing columns, so this
        // keeps working if the settings table gains a required field.
        $spec = SettingsSchema::spec('booking.stay_checkout_enabled');

        Setting::query()->updateOrCreate(
            ['key' => 'booking.stay_checkout_enabled'],
            [
                'group' => $spec['group'],
                'type' => $spec['type'],
                'label' => $spec['label'],
                'value' => '1',
                'default_value' => '0',
                'is_public' => false,
                'is_sensitive' => false,
            ],
        );

        app(SettingsRepository::class)->forget('booking.stay_checkout_enabled');
    }
}
