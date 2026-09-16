<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * The admin menu: sections with dropdown items, every item an EXISTING route.
 *
 * Nothing here creates a page. Items that differ only by a filter point at the
 * same list screen with the query that screen already understands (the
 * activity log's ?group=, the listings' ?status=). Each item carries the
 * permission its route's middleware enforces — the same key, deliberately — so
 * nobody is shown an item that 403s.
 */
final class AdminNavigation
{
    /**
     * @return list<array{key: string, label: string, routes: list<string>, items: list<array<string, mixed>>}>
     */
    public static function definition(): array
    {
        return [
            [
                'key' => 'members', 'label' => 'Members',
                // Screens reached from these items, so the section stays lit on them.
                'routes' => ['admin.members.*', 'admin.users.show', 'admin.users.edit', 'admin.users.login-history', 'admin.fulfillment.*', 'admin.inbox.*', 'admin.search'],
                'items' => [
                    ['label' => 'All members', 'route' => 'admin.users.index', 'query' => ['role' => 'member'], 'permission' => 'users.view'],
                    ['label' => 'All accounts', 'route' => 'admin.users.index', 'query' => [], 'permission' => 'users.view'],
                    ['label' => 'Add member', 'route' => 'admin.users.create', 'permission' => 'users.create'],
                    ['label' => 'Member activity', 'route' => 'admin.activity.log', 'query' => ['group' => 'members'], 'permission' => 'audit.view', 'mirror' => true],
                    ['label' => 'First login records', 'route' => 'admin.fulfillment.index', 'query' => ['view' => 'first-login'], 'permission' => 'members.view'],
                    ['label' => 'Incentive records', 'route' => 'admin.fulfillment.index', 'query' => ['view' => 'incentives'], 'permission' => 'members.view'],
                    ['label' => 'Advertisement acceptance', 'route' => 'admin.fulfillment.index', 'query' => ['view' => 'acceptance'], 'permission' => 'members.view'],
                    ['label' => 'Fulfillment records', 'route' => 'admin.fulfillment.index', 'query' => ['view' => 'fulfillment'], 'permission' => 'members.view'],
                    ['label' => 'Inbox (form submissions)', 'route' => 'admin.inbox.index', 'permission' => 'inbox.view'],
                ],
            ],
            [
                'key' => 'advertisements', 'label' => 'Advertisements',
                'routes' => ['admin.properties.*', 'admin.media.*', 'admin.offers.*', 'admin.activity.index'],
                'items' => [
                    ['label' => 'All advertisements', 'route' => 'admin.properties.index', 'query' => [], 'permission' => 'properties.view'],
                    ['label' => 'Create advertisement', 'route' => 'admin.properties.create', 'permission' => 'properties.create'],
                    ['label' => 'Active advertisements', 'route' => 'admin.properties.index', 'query' => ['status' => 'active'], 'permission' => 'properties.view'],
                    ['label' => 'Pending advertisements', 'route' => 'admin.properties.index', 'query' => ['status' => 'pending_review'], 'permission' => 'properties.view'],
                    ['label' => 'Advertisement activity', 'route' => 'admin.activity.log', 'query' => ['group' => 'ads'], 'permission' => 'audit.view', 'mirror' => true],
                    ['label' => 'Listing analytics & click tracking', 'route' => 'admin.activity.index', 'permission' => 'reports.view'],
                    ['label' => 'Offers / inquiries', 'route' => 'admin.offers.index', 'permission' => 'offers.view'],
                    ['label' => 'Photo library', 'route' => 'admin.media.index', 'permission' => 'media.view'],
                ],
            ],
            [
                'key' => 'contracts', 'label' => 'Contracts',
                'routes' => ['admin.contracts.*'],
                'items' => [
                    ['label' => 'All contracts', 'route' => 'admin.contracts.index', 'query' => [], 'permission' => 'contracts.view'],
                    ['label' => 'Signed contracts', 'route' => 'admin.contracts.index', 'query' => ['status' => 'completed'], 'permission' => 'contracts.view'],
                    ['label' => 'Pending contracts', 'route' => 'admin.contracts.index', 'query' => ['status' => 'sent'], 'permission' => 'contracts.view'],
                    ['label' => 'Acceptance records', 'route' => 'admin.fulfillment.index', 'query' => ['view' => 'acceptance'], 'permission' => 'members.view', 'mirror' => true],
                    ['label' => 'Fulfillment certificates', 'route' => 'admin.fulfillment.index', 'query' => ['view' => 'certificates'], 'permission' => 'members.view'],
                ],
            ],
            [
                'key' => 'payments', 'label' => 'Payments',
                'routes' => ['admin.member-services.*', 'admin.hosting.*'],
                'items' => [
                    ['label' => 'Payments (Member Services orders)', 'route' => 'admin.member-services.index', 'query' => [], 'permission' => 'billing.view'],
                    ['label' => 'Paid orders', 'route' => 'admin.member-services.index', 'query' => ['status' => 'paid'], 'permission' => 'billing.view'],
                    ['label' => 'Payment activity', 'route' => 'admin.activity.log', 'query' => ['group' => 'payments'], 'permission' => 'audit.view'],
                    ['label' => 'Service fees', 'route' => 'admin.hosting.service-fees', 'permission' => 'billing.service_fees'],
                ],
            ],
            [
                'key' => 'activity', 'label' => 'Activity & Tracking',
                'routes' => ['admin.activity.log', 'admin.activity.session', 'admin.activity.map'],
                'items' => [
                    ['label' => 'All activity', 'route' => 'admin.activity.log', 'query' => [], 'permission' => 'audit.view'],
                    ['label' => 'Visitors', 'route' => 'admin.activity.log', 'query' => ['group' => 'visitors'], 'permission' => 'audit.view'],
                    ['label' => 'Member activity', 'route' => 'admin.activity.log', 'query' => ['group' => 'members'], 'permission' => 'audit.view'],
                    ['label' => 'Login activity', 'route' => 'admin.activity.log', 'query' => ['group' => 'logins'], 'permission' => 'audit.view'],
                    ['label' => 'Advertisement activity', 'route' => 'admin.activity.log', 'query' => ['group' => 'ads'], 'permission' => 'audit.view'],
                    ['label' => 'Payment activity', 'route' => 'admin.activity.log', 'query' => ['group' => 'payments'], 'permission' => 'audit.view', 'mirror' => true],
                    ['label' => 'Offers', 'route' => 'admin.activity.log', 'query' => ['group' => 'offers'], 'permission' => 'audit.view'],
                    ['label' => 'Contracts', 'route' => 'admin.activity.log', 'query' => ['group' => 'contracts'], 'permission' => 'audit.view'],
                    ['label' => 'Admin activity', 'route' => 'admin.activity.log', 'query' => ['group' => 'admin'], 'permission' => 'audit.view'],
                    ['label' => 'Map view', 'route' => 'admin.activity.map', 'permission' => 'audit.view'],
                ],
            ],
            [
                'key' => 'marketing', 'label' => 'Marketing',
                'routes' => ['admin.incentives.*'],
                'items' => [
                    ['label' => 'Incentive offers', 'route' => 'admin.incentives.index', 'permission' => 'members.view'],
                    ['label' => 'Incentive records', 'route' => 'admin.fulfillment.index', 'query' => ['view' => 'incentives'], 'permission' => 'members.view', 'mirror' => true],
                    ['label' => 'Click tracking', 'route' => 'admin.activity.index', 'permission' => 'reports.view', 'mirror' => true],
                    ['label' => 'Marketing activity (CTA clicks)', 'route' => 'admin.activity.log', 'query' => ['type' => 'cta_click'], 'permission' => 'audit.view'],
                ],
            ],
            [
                'key' => 'administration', 'label' => 'Administration',
                'routes' => ['admin.roles.*', 'admin.settings.*', 'admin.demo-data.*'],
                'items' => [
                    ['label' => 'Admin users', 'route' => 'admin.users.index', 'query' => ['role' => 'admin'], 'permission' => 'users.view'],
                    ['label' => 'Super admins', 'route' => 'admin.users.index', 'query' => ['role' => 'super_admin'], 'permission' => 'users.view'],
                    ['label' => 'Roles & permissions', 'route' => 'admin.roles.index', 'permission' => 'roles.view'],
                    ['label' => 'System settings', 'route' => 'admin.settings.index', 'permission' => 'settings.view'],
                    ['label' => 'Admin activity (audit)', 'route' => 'admin.activity.log', 'query' => ['group' => 'admin'], 'permission' => 'audit.view', 'mirror' => true],
                    ['label' => 'Demo data', 'route' => 'admin.demo-data.index', 'permission' => 'settings.view', 'superAdmin' => true],
                    ['label' => 'Staff guide (PDF) ↓', 'route' => 'staff-guide', 'permission' => null],
                ],
            ],
        ];
    }

    /**
     * The definition filtered to what this user may open, with current-page
     * state resolved. Empty for anyone who is not staff.
     *
     * @return list<array<string, mixed>>
     */
    public static function for(?User $user, Request $request): array
    {
        if (! $user) {
            return [];
        }

        $sections = [];

        foreach (self::definition() as $section) {
            $items = array_values(array_filter($section['items'], fn (array $item) =>
                Route::has($item['route'])
                && ($item['permission'] === null ? $user->isStaff() : $user->hasPermission($item['permission']))
                && (! ($item['superAdmin'] ?? false) || $user->isSuperAdmin())
            ));

            if ($items !== []) {
                $section['items'] = $items;
                $sections[] = $section;
            }
        }

        return self::markCurrent($sections, $request);
    }

    /**
     * One current item at most: the best match for this route and query.
     *
     * An item matches when the route matches and every query key it declares
     * equals the request's value (an empty-query item means "unfiltered", so a
     * filtered request prefers its filtered sibling). Mirrors — the same link
     * listed under a second section — never win, so a page lights one section.
     */
    private static function markCurrent(array $sections, Request $request): array
    {
        $best = null;
        $bestScore = -1;

        foreach ($sections as $s => $section) {
            foreach ($section['items'] as $i => $item) {
                if (! $request->routeIs($item['route']) || ($item['mirror'] ?? false)) {
                    continue;
                }

                $declared = $item['query'] ?? null;
                $score = 0;

                if (is_array($declared)) {
                    foreach ($declared as $key => $value) {
                        if ((string) $request->query($key, '') !== (string) $value) {
                            continue 2;
                        }
                        $score++;
                    }

                    // An unfiltered item must not claim a filtered request.
                    if ($declared === [] && self::requestHasFilterFor($sections, $item['route'], $request)) {
                        continue;
                    }
                }

                if ($score > $bestScore) {
                    [$best, $bestScore] = [[$s, $i], $score];
                }
            }
        }

        foreach ($sections as $s => &$section) {
            foreach ($section['items'] as $i => &$item) {
                $item['current'] = $best === [$s, $i];
            }
            unset($item);

            $section['current'] = ($best !== null && $best[0] === $s)
                || ($best === null && collect($section['routes'])->contains(fn ($p) => $request->routeIs($p)));
        }
        unset($section);

        return $sections;
    }

    private static function requestHasFilterFor(array $sections, string $route, Request $request): bool
    {
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                if ($item['route'] !== $route || empty($item['query'])) {
                    continue;
                }

                foreach (array_keys($item['query']) as $key) {
                    if ($request->filled($key) && $request->query($key) !== 'all') {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
