{{--
    Staff navigation.

    Every admin screen existed and was reachable only by typing its URL — there
    was no link to any of them from anywhere in the product. "Where do I create
    a user?" had no answer that did not involve knowing the route name.

    Each entry is gated on the permission its route already enforces, so nobody
    is shown a tab that 403s when they click it. The gate here and the
    middleware on the route are the same key, deliberately: a tab that appears
    when the page refuses to open is worse than no tab.

    Renders nothing at all for a host or member, who share this layout.
--}}
@php
    $adminTabs = collect([
        ['label' => 'Users',     'route' => 'admin.users.index',            'permission' => 'users.view'],
        ['label' => 'Roles',     'route' => 'admin.roles.index',            'permission' => 'roles.view'],
        ['label' => 'Listings',  'route' => 'admin.properties.index',       'permission' => 'properties.view'],
        ['label' => 'Media',     'route' => 'admin.media.index',            'permission' => 'media.view'],
        ['label' => 'Offers',    'route' => 'admin.offers.index',           'permission' => 'offers.view'],
        ['label' => 'Orders',    'route' => 'admin.member-services.index',  'permission' => 'billing.view'],
        ['label' => 'Contracts', 'route' => 'admin.contracts.index',        'permission' => 'contracts.view'],
        ['label' => 'Inbox',     'route' => 'admin.inbox.index',            'permission' => 'inbox.view'],
        ['label' => 'Activity',  'route' => 'admin.activity.index',         'permission' => 'audit.view'],
        ['label' => 'Activity & IP logs', 'route' => 'admin.activity.log', 'permission' => 'audit.view'],
        ['label' => 'Settings',  'route' => 'admin.settings.index',         'permission' => 'settings.view'],
    ])->filter(fn ($tab) =>
        // A route that has not been defined must not take the page down with
        // it; this partial is on every dashboard screen.
        \Illuminate\Support\Facades\Route::has($tab['route'])
        && auth()->user()?->hasPermission($tab['permission'])
    );
@endphp

@if ($adminTabs->isNotEmpty())
    <nav class="vyt-adminnav" aria-label="Admin sections">
        <div class="vyt-adminnav-inner">
            @foreach ($adminTabs as $tab)
                @php($isCurrent = request()->routeIs(str_replace('.index', '.*', $tab['route'])))
                <a href="{{ route($tab['route']) }}"
                   class="{{ $isCurrent ? 'is-current' : '' }}"
                   @if ($isCurrent) aria-current="page" @endif>{{ $tab['label'] }}</a>
            @endforeach

            {{-- Not gated on a permission: a new starter with the narrowest
                 role is exactly who needs it, and it contains nothing beyond
                 the shape of the admin area. Generated fresh on each download,
                 so it describes this environment as configured today. --}}
            <a href="{{ route('staff-guide') }}"
               title="Download the staff training guide as a PDF">Staff guide ↓</a>

            @if (auth()->user()?->hasPermission('users.create'))
                {{-- The thing this navigation was added for. Kept visually
                     distinct because it is an action, not a section. --}}
                <a href="{{ route('admin.users.create') }}" class="vyt-adminnav-cta">+ New user</a>
            @endif
        </div>
    </nav>
@endif
