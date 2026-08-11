<?php

namespace Database\Seeders;

use App\Enums\FeeStructure;
use App\Models\ServiceFeeConfig;
use App\Services\Bookings\QuoteCalculator;
use App\Services\Fees\ServiceFeeResolver;
use Illuminate\Database\Seeder;

/**
 * Seeds the platform default fee configuration.
 *
 * This is a STARTING POINT, not a constant — the whole point of the
 * service_fee_configs table is that an operator changes these from
 * Admin → Hosting → Service Fees without a deploy. Seeded with firstOrCreate
 * so re-running never overwrites a rate someone has since tuned.
 */
class ServiceFeeSeeder extends Seeder
{
    public function run(): void
    {
        ServiceFeeConfig::query()->firstOrCreate(
            [
                'scope_type' => ServiceFeeConfig::SCOPE_DEFAULT,
                'scope_value' => null,
            ],
            [
                'name' => 'Platform default',
                'fee_structure' => FeeStructure::Split,
                'split_host_bps' => 300,     // 3%
                'split_guest_bps' => 1500,   // 15% — mid-band of 14.1%–16.5%
                'single_host_bps' => 1550,   // 15.5%
                'active' => true,
                'notes' => 'Default applied when no property, host or listing-source override matches.',
            ],
        );

        ServiceFeeResolver::bustCache();
        QuoteCalculator::bustCache();

        $this->command?->info('  ✓ service fee configuration (platform default)');
    }
}
