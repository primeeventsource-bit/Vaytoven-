<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\Fulfillment\AdvertisementFulfillment;
use App\Services\Fulfillment\FulfillmentTimeline;
use App\Services\Fulfillment\MemberIncentive;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Every Managed Listing Program client's evidence in one list.
 *
 * The per-member evidence lives on Member 360; nothing listed it ACROSS
 * members, so "who has not accepted yet" meant opening profiles one by one.
 * One page with views, rather than a page per menu item: first logins,
 * incentives, acceptance and certificates are the same rows read differently.
 * Read-only — nothing here records anything.
 */
class FulfillmentRegisterController extends Controller
{
    public const VIEWS = [
        'fulfillment'  => 'Fulfillment records',
        'first-login'  => 'First login records',
        'incentives'   => 'Incentive records',
        'acceptance'   => 'Advertisement acceptance',
        'certificates' => 'Fulfillment certificates',
    ];

    public function index(
        Request $request,
        AdvertisementFulfillment $fulfillment,
        MemberIncentive $incentive,
        FulfillmentTimeline $timeline,
    ): View {
        $view = array_key_exists($request->query('view'), self::VIEWS) ? $request->query('view') : 'fulfillment';
        $q    = trim((string) $request->query('q', ''));

        $clients = User::query()
            ->whereNotIn('role', TrackingEvent::STAFF_ROLES)
            ->where(fn ($w) => $w->where('role', UserRole::Member->value)
                ->orWhereHas('hostProperties', fn ($p) => $p->where('status', PropertyStatus::Active->value)))
            ->when($q !== '', fn ($w) => $w->where(fn ($i) => $i
                ->where('email', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhere('member_id', 'like', "%{$q}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $rows = $clients->getCollection()->map(fn (User $u) => [
            'member'      => $u,
            'firstLogin'  => $timeline->firstLogin($u),
            'incentive'   => $incentive->state($u),
            'ads'         => $fulfillment->forMember($u),
        ]);

        return view('admin.fulfillment.index', [
            'views'   => self::VIEWS,
            'view'    => $view,
            'q'       => $q,
            'clients' => $clients,
            'rows'    => $rows,
        ]);
    }
}
