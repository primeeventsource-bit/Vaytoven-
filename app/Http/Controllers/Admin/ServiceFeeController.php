<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FeeStructure;
use App\Http\Controllers\Controller;
use App\Models\ServiceFeeConfig;
use App\Services\AdminAuditLogService;
use App\Services\Bookings\QuoteCalculator;
use App\Services\Fees\ResolvedServiceFee;
use App\Services\Fees\ServiceFeeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin → Hosting → Service Fees.
 *
 * Nothing about the rates is hard-coded in the application: every percentage a
 * booking uses comes from a row this screen manages. The seeded defaults are a
 * starting point, not a constant.
 */
class ServiceFeeController extends Controller
{
    public function index(): View
    {
        return view('admin.hosting.service-fees', [
            'configs' => ServiceFeeConfig::query()
                ->with('updatedBy:id,name')
                ->orderByRaw("CASE scope_type
                    WHEN 'property' THEN 1 WHEN 'host' THEN 2
                    WHEN 'listing_source' THEN 3 ELSE 4 END")
                ->orderByDesc('effective_from')
                ->get(),
            'structures' => FeeStructure::options(),
            'guestMin' => ServiceFeeConfig::GUEST_BPS_MIN,
            'guestMax' => ServiceFeeConfig::GUEST_BPS_MAX,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $config = ServiceFeeConfig::query()->create($data + [
            'updated_by_user_id' => $request->user()->id,
        ]);

        $this->audit($request, 'service_fee.create', $config, ['new_value' => $data]);

        return back()->with('success', "Fee configuration “{$config->name}” created.");
    }

    public function update(Request $request, ServiceFeeConfig $config): RedirectResponse
    {
        $before = $config->only([
            'fee_structure', 'split_host_bps', 'split_guest_bps', 'single_host_bps', 'active',
        ]);

        $data = $this->validated($request, $config);

        $config->update($data + ['updated_by_user_id' => $request->user()->id]);

        $this->audit($request, 'service_fee.update', $config, [
            'old_value' => $before,
            'new_value' => $data,
        ]);

        return back()->with('success', "Fee configuration “{$config->name}” updated.");
    }

    public function destroy(Request $request, ServiceFeeConfig $config): RedirectResponse
    {
        // The platform default is the last line of resolution — removing it
        // would silently drop every quote onto the hard-coded fallback.
        if ($config->scope_type === ServiceFeeConfig::SCOPE_DEFAULT) {
            return back()->with('error', 'The platform default configuration cannot be deleted. Edit it instead.');
        }

        $snapshot = $config->only(['name', 'scope_type', 'scope_value', 'fee_structure']);
        $config->delete();

        $this->audit($request, 'service_fee.delete', null, $snapshot);

        return back()->with('success', 'Fee configuration deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ServiceFeeConfig $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'scope_type' => ['required', Rule::in([
                ServiceFeeConfig::SCOPE_DEFAULT, ServiceFeeConfig::SCOPE_LISTING_SOURCE,
                ServiceFeeConfig::SCOPE_HOST, ServiceFeeConfig::SCOPE_PROPERTY,
            ])],
            'scope_value' => ['nullable', 'string', 'max:64', 'required_unless:scope_type,default'],
            'fee_structure' => ['required', Rule::enum(FeeStructure::class)],

            // Rates are entered as percentages in the UI and converted to
            // basis points here — operators think in percent, the database
            // stores integers.
            'split_host_pct' => ['required', 'numeric', 'between:0,50'],
            'split_guest_pct' => ['required', 'numeric', 'between:0,50'],
            'single_host_pct' => ['required', 'numeric', 'between:0,50'],

            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $splitGuestBps = $this->toBps($data['split_guest_pct']);

        // The band is a product rule, not a UI nicety — enforced here so it
        // holds for any caller, and again on the model's constants.
        if ($splitGuestBps < ServiceFeeConfig::GUEST_BPS_MIN || $splitGuestBps > ServiceFeeConfig::GUEST_BPS_MAX) {
            abort(422, sprintf(
                'The Split-Fee guest rate must be between %s and %s.',
                ResolvedServiceFee::formatBps(ServiceFeeConfig::GUEST_BPS_MIN),
                ResolvedServiceFee::formatBps(ServiceFeeConfig::GUEST_BPS_MAX),
            ));
        }

        return [
            'name' => $data['name'],
            'scope_type' => $data['scope_type'],
            'scope_value' => $data['scope_type'] === ServiceFeeConfig::SCOPE_DEFAULT
                ? null : $data['scope_value'],
            'fee_structure' => $data['fee_structure'],
            'split_host_bps' => $this->toBps($data['split_host_pct']),
            'split_guest_bps' => $splitGuestBps,
            'single_host_bps' => $this->toBps($data['single_host_pct']),
            'effective_from' => $data['effective_from'] ?? null,
            'effective_to' => $data['effective_to'] ?? null,
            'active' => $request->boolean('active'),
            'notes' => $data['notes'] ?? null,
        ];
    }

    /** "15.5" -> 1550. Rounds at the last integer basis point. */
    private function toBps(string|float|int $percent): int
    {
        return (int) round(((float) $percent) * 100);
    }

    private function audit(Request $request, string $action, ?ServiceFeeConfig $config, array $payload): void
    {
        AdminAuditLogService::log(
            actor: $request->user(),
            action: $action,
            subject: $config,
            payload: $payload,
            ipAddress: $request->ip(),
        );

        // Quotes read these on every page view; both caches must drop.
        ServiceFeeResolver::bustCache();
        QuoteCalculator::bustCache();
    }
}
