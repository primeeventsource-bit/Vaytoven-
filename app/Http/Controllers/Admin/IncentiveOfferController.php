<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdvertisementFulfillmentRecord as Record;
use App\Models\User;
use App\Services\Fulfillment\IncentiveCatalog;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Marketing → Incentive offers: every enrollment thank-you, previewed as the
 * client sees it, and which one new clients are sent by default. Individual
 * clients can be given a different offer from their profile.
 */
class IncentiveOfferController extends Controller
{
    public function index(): View
    {
        $counts = Record::query()
            ->whereIn('event', [Record::EVENT_INCENTIVE_PRESENTED, Record::EVENT_INCENTIVE_ACKNOWLEDGED, Record::EVENT_INCENTIVE_DELIVERED])
            ->selectRaw('incentive_key, event, count(*) as c')
            ->groupBy('incentive_key', 'event')
            ->get()
            ->groupBy('incentive_key')
            ->map(fn ($rows) => $rows->pluck('c', 'event'));

        return view('admin.incentives.index', [
            'offers'     => IncentiveCatalog::all(),
            'defaultKey' => IncentiveCatalog::defaultKey(),
            'counts'     => $counts,
            'assigned'   => User::query()->whereNotNull('incentive_key')
                ->selectRaw('incentive_key, count(*) as c')->groupBy('incentive_key')->pluck('c', 'incentive_key'),
        ]);
    }

    public function setDefault(Request $request, SettingsRepository $settings): RedirectResponse
    {
        $validated = $request->validate([
            'incentive_key' => ['required', 'in:'.implode(',', IncentiveCatalog::keys())],
        ]);

        // Audited by the repository (setting.update).
        $settings->set(IncentiveCatalog::SETTING_DEFAULT, $validated['incentive_key'], $request->user(), $request->ip());

        return redirect()->route('admin.incentives.index')
            ->with('success', IncentiveCatalog::find($validated['incentive_key'])->name.' is now sent to new clients.');
    }
}
