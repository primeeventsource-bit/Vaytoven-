<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\CancellationPolicyConfig;
use App\Models\FeeSchedule;
use App\Models\PropertyType;
use App\Models\TaxRule;
use App\Models\VacationClubProgram;
use App\Services\AdminAuditLogService;
use App\Services\Bookings\QuoteCalculator;
use App\Services\Payments\RefundCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * One controller for the simple managed collections (Layer 2). Each entry
 * in COLLECTIONS declares its model and a field spec; the shared
 * admin.settings.collection view renders the table and forms from that
 * spec, so adding a collection here is all it takes to get a manager UI.
 *
 * Rows are never hard-deleted — collections that support it are switched
 * off via their `active` flag so historical bookings/enquiries keep their
 * references.
 *
 * Field spec: name => [type, label, rules, options?]. Types the view
 * understands: text, textarea, number, cents (dollars in the UI), bool,
 * select, json.
 */
class ConfigCollectionController extends Controller
{
    public const COLLECTIONS = [
        'cancellation-policies' => [
            'model' => CancellationPolicyConfig::class,
            'title' => 'Cancellation policies',
            'order_by' => 'id',
            'fields' => [
                'code' => ['text', 'Code', ['required', 'string', 'max:32', 'regex:/^[a-z0-9_]+$/']],
                'name' => ['text', 'Name', ['required', 'string', 'max:96']],
                'description' => ['textarea', 'Description', ['nullable', 'string', 'max:1000']],
                'refund_tiers' => ['json', 'Refund tiers', ['nullable', 'array'], null, '[{"days_before":5,"refund_pct":100},{"days_before":1,"refund_pct":50}]'],
                'is_default' => ['bool', 'Default policy', ['boolean']],
                'active' => ['bool', 'Active', ['boolean']],
            ],
            'columns' => ['code', 'name', 'is_default', 'active'],
            'single_default' => true,
        ],
        'fee-schedules' => [
            'model' => FeeSchedule::class,
            'title' => 'Fee schedules',
            'order_by' => 'id',
            'fields' => [
                'name' => ['text', 'Name', ['required', 'string', 'max:96']],
                'guest_service_pct' => ['number', 'Guest service fee %', ['required', 'integer', 'between:0,50']],
                'host_commission_pct' => ['number', 'Host commission %', ['required', 'integer', 'between:0,50']],
                'cleaning_fee_mode' => ['select', 'Cleaning fee mode', ['required', 'in:host_set,flat,none'], ['host_set', 'flat', 'none']],
                'flat_cleaning_fee_cents' => ['cents', 'Flat cleaning fee', ['nullable', 'integer', 'min:0']],
                'security_deposit_mode' => ['select', 'Security deposit mode', ['required', 'in:none,flat,pct'], ['none', 'flat', 'pct']],
                'security_deposit_cents' => ['cents', 'Security deposit', ['nullable', 'integer', 'min:0']],
                'applies_to' => ['select', 'Applies to', ['required', 'in:all,host,mlp'], ['all', 'host', 'mlp']],
                'active' => ['bool', 'Active', ['boolean']],
            ],
            'columns' => ['name', 'guest_service_pct', 'host_commission_pct', 'applies_to', 'active'],
        ],
        'tax-rules' => [
            'model' => TaxRule::class,
            'title' => 'Tax rules',
            'order_by' => 'id',
            'fields' => [
                'name' => ['text', 'Name', ['required', 'string', 'max:96']],
                'jurisdiction' => ['text', 'Jurisdiction', ['required', 'string', 'max:96']],
                'country_code' => ['text', 'Country code', ['required', 'string', 'size:2']],
                'region_code' => ['text', 'Region code', ['nullable', 'string', 'max:8']],
                'rate_bps' => ['number', 'Rate (basis points)', ['required', 'integer', 'between:0,5000'], null, '875 = 8.75%'],
                'applies_to' => ['select', 'Applies to', ['required', 'in:booking_total,service_fee,nightly'], ['booking_total', 'service_fee', 'nightly']],
                'active' => ['bool', 'Active', ['boolean']],
            ],
            'columns' => ['name', 'jurisdiction', 'rate_bps', 'applies_to', 'active'],
        ],
        'club-programs' => [
            'model' => VacationClubProgram::class,
            'title' => 'Vacation club programs',
            'order_by' => 'sort_order',
            'fields' => [
                'name' => ['text', 'Name', ['required', 'string', 'max:128']],
                'code' => ['text', 'Code', ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/']],
                'points_based' => ['bool', 'Points-based', ['boolean']],
                'active' => ['bool', 'Active', ['boolean']],
                'enquiry_notes' => ['textarea', 'Enquiry notes', ['nullable', 'string', 'max:2000']],
                'sort_order' => ['number', 'Sort order', ['nullable', 'integer', 'between:0,10000']],
            ],
            'columns' => ['name', 'code', 'points_based', 'active'],
        ],
        'property-types' => [
            'model' => PropertyType::class,
            'title' => 'Property types',
            'order_by' => 'sort_order',
            'fields' => [
                'slug' => ['text', 'Slug', ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/']],
                'label' => ['text', 'Label', ['required', 'string', 'max:96']],
                'icon' => ['text', 'Icon', ['nullable', 'string', 'max:64']],
                'active' => ['bool', 'Active', ['boolean']],
                'sort_order' => ['number', 'Sort order', ['nullable', 'integer', 'between:0,10000']],
            ],
            'columns' => ['slug', 'label', 'active'],
        ],
        'amenities' => [
            'model' => Amenity::class,
            'title' => 'Amenities',
            'order_by' => 'slug',
            'fields' => [
                'slug' => ['text', 'Slug', ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/']],
                'label' => ['text', 'Label', ['required', 'string', 'max:128']],
                'category' => ['select', 'Category', ['required', 'in:safety,accessibility,outdoor,indoor,family,workspace,other'], ['safety', 'accessibility', 'outdoor', 'indoor', 'family', 'workspace', 'other']],
                'icon' => ['text', 'Icon', ['nullable', 'string', 'max:64']],
            ],
            'columns' => ['slug', 'label', 'category'],
        ],
    ];

    public function index(string $collection): View
    {
        $config = $this->configFor($collection);

        return view('admin.settings.collection', [
            'collection' => $collection,
            'config' => $config,
            'rows' => $config['model']::query()->orderBy($config['order_by'])->get(),
        ]);
    }

    public function store(Request $request, string $collection): RedirectResponse
    {
        $config = $this->configFor($collection);
        $data = $this->validated($request, $config);

        $row = DB::transaction(function () use ($config, $data) {
            $this->enforceSingleDefault($config, $data);

            return $config['model']::create($data);
        });

        AdminAuditLogService::log(
            actor: $request->user(),
            action: str_replace('-', '_', $collection).'.create',
            subject: $row,
            payload: ['new_value' => $data],
            ipAddress: $request->ip(),
        );

        $this->bustCaches($collection);

        return redirect()->route('admin.settings.collections.index', $collection)
            ->with('success', ucfirst(str_replace('-', ' ', $collection)).' entry created.');
    }

    public function update(Request $request, string $collection, int $id): RedirectResponse
    {
        $config = $this->configFor($collection);
        $row = $config['model']::query()->findOrFail($id);
        $data = $this->validated($request, $config, $row);

        $before = $row->only(array_keys($config['fields']));

        DB::transaction(function () use ($config, $data, $row) {
            $this->enforceSingleDefault($config, $data, $row);
            $row->fill($data)->save();
        });

        AdminAuditLogService::log(
            actor: $request->user(),
            action: str_replace('-', '_', $collection).'.update',
            subject: $row,
            payload: ['old_value' => $before, 'new_value' => $data],
            ipAddress: $request->ip(),
        );

        $this->bustCaches($collection);

        return redirect()->route('admin.settings.collections.index', $collection)
            ->with('success', ucfirst(str_replace('-', ' ', $collection)).' entry updated.');
    }

    private function configFor(string $collection): array
    {
        abort_unless(array_key_exists($collection, self::COLLECTIONS), 404);

        return self::COLLECTIONS[$collection];
    }

    /** Read-path caches that must reflect collection edits immediately. */
    private function bustCaches(string $collection): void
    {
        match ($collection) {
            'fee-schedules', 'tax-rules' => QuoteCalculator::bustCache(),
            'cancellation-policies' => RefundCalculator::bustCache(),
            default => null,
        };
    }

    private function validated(Request $request, array $config, ?Model $row = null): array
    {
        $rules = [];
        foreach ($config['fields'] as $field => [$type, $label, $fieldRules]) {
            $rules[$field] = $fieldRules;
        }

        $data = $request->validate($rules);

        foreach ($config['fields'] as $field => [$type, $label, $fieldRules]) {
            if (! array_key_exists($field, $data)) {
                if ($type === 'bool') {
                    $data[$field] = false; // unchecked checkboxes are absent
                }

                continue;
            }
            if ($type === 'bool') {
                $data[$field] = (bool) $data[$field];
            }
            if ($type === 'cents' && $data[$field] !== null && $data[$field] !== '') {
                $data[$field] = (int) $data[$field];
            }
            if ($type === 'json' && is_string($data[$field])) {
                $decoded = json_decode($data[$field], true);
                abort_if($decoded === null && trim($data[$field]) !== '' && trim($data[$field]) !== 'null', 422, "Field {$label} must be valid JSON.");
                $data[$field] = $decoded ?? [];
            }
        }

        return $data;
    }

    /** Collections flagged single_default keep exactly one is_default row. */
    private function enforceSingleDefault(array $config, array $data, ?Model $current = null): void
    {
        if (! ($config['single_default'] ?? false) || ! ($data['is_default'] ?? false)) {
            return;
        }

        $config['model']::query()
            ->when($current, fn ($q) => $q->whereKeyNot($current->getKey()))
            ->update(['is_default' => false]);
    }
}
