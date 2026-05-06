<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Operations dashboard
            <span class="ml-2 inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-purple-100 text-purple-700">{{ auth()->user()->role->value }}</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            {{-- Summary tiles -------------------------------------------------- --}}
            <section class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <x-admin-tile label="New enquiries" :value="$enquiriesNew" href="#enquiries" tone="pink" />
                <x-admin-tile label="Open tickets" :value="$ticketsOpen" tone="amber" />
                <x-admin-tile label="Open disputes" :value="$disputesOpen" tone="red" />
                <x-admin-tile label="Help articles" :value="$helpArticleCount" href="/help" tone="purple" />

                <x-admin-tile label="Charges 7d" :value="'$'.number_format($chargesLast7dCents/100)" tone="emerald" />
                <x-admin-tile label="Refunds 7d" :value="'$'.number_format($refundsLast7dCents/100)" tone="slate" />
                <x-admin-tile label="Tracking events 24h" :value="number_format($trackingEvents24h)" tone="blue" />
                <x-admin-tile label="Suspicious logins 24h" :value="$suspiciousLogins24h" tone="red" />
            </section>

            {{-- Bookings + Users --------------------------------------------- --}}
            <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Bookings by status</h3>
                    @if ($bookingsByStatus->isEmpty())
                        <p class="text-sm text-gray-500">No bookings yet.</p>
                    @else
                        <ul class="space-y-2">
                            @foreach ($bookingsByStatus as $status => $count)
                                <li class="flex justify-between text-sm">
                                    <span class="text-gray-700">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                                    <span class="font-semibold">{{ $count }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Users by role</h3>
                    @if ($usersByRole->isEmpty())
                        <p class="text-sm text-gray-500">No users yet.</p>
                    @else
                        <ul class="space-y-2">
                            @foreach ($usersByRole as $role => $count)
                                <li class="flex justify-between text-sm">
                                    <span class="text-gray-700">{{ ucfirst(str_replace('_', ' ', $role)) }}</span>
                                    <span class="font-semibold">{{ $count }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            {{-- Recent member enquiries ------------------------------------- --}}
            <section class="bg-white rounded-lg shadow-sm" id="enquiries">
                <header class="flex items-center justify-between px-6 py-4 border-b">
                    <h3 class="font-semibold text-gray-900">Recent member enquiries</h3>
                    <span class="text-xs text-gray-500">Latest 5</span>
                </header>
                @if ($enquiriesRecent->isEmpty())
                    <p class="px-6 py-8 text-sm text-gray-500">No enquiries yet — the form on the landing posts here.</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="text-left px-6 py-2 font-medium">Reference</th>
                                <th class="text-left px-6 py-2 font-medium">Name</th>
                                <th class="text-left px-6 py-2 font-medium">Club</th>
                                <th class="text-left px-6 py-2 font-medium">Status</th>
                                <th class="text-left px-6 py-2 font-medium">Submitted</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($enquiriesRecent as $enquiry)
                                <tr>
                                    <td class="px-6 py-3 font-mono text-xs text-purple-700">{{ $enquiry->reference }}</td>
                                    <td class="px-6 py-3">{{ $enquiry->fullName() }}</td>
                                    <td class="px-6 py-3 text-gray-600">{{ $enquiry->club }}</td>
                                    <td class="px-6 py-3"><span class="text-xs px-2 py-0.5 rounded bg-gray-100">{{ $enquiry->status->value }}</span></td>
                                    <td class="px-6 py-3 text-gray-500 text-xs">{{ $enquiry->created_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            {{-- Recent bookings ---------------------------------------------- --}}
            <section class="bg-white rounded-lg shadow-sm">
                <header class="flex items-center justify-between px-6 py-4 border-b">
                    <h3 class="font-semibold text-gray-900">Recent bookings</h3>
                    <span class="text-xs text-gray-500">Latest 5</span>
                </header>
                @if ($bookingsRecent->isEmpty())
                    <p class="px-6 py-8 text-sm text-gray-500">No bookings yet.</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="text-left px-6 py-2 font-medium">Code</th>
                                <th class="text-left px-6 py-2 font-medium">Property</th>
                                <th class="text-left px-6 py-2 font-medium">Status</th>
                                <th class="text-left px-6 py-2 font-medium">Total</th>
                                <th class="text-left px-6 py-2 font-medium">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($bookingsRecent as $booking)
                                <tr>
                                    <td class="px-6 py-3 font-mono text-xs text-purple-700">{{ $booking->confirmation_code }}</td>
                                    <td class="px-6 py-3">{{ $booking->property?->title ?? '—' }}</td>
                                    <td class="px-6 py-3"><span class="text-xs px-2 py-0.5 rounded bg-gray-100">{{ $booking->status->value }}</span></td>
                                    <td class="px-6 py-3">${{ number_format($booking->total_cents / 100, 2) }}</td>
                                    <td class="px-6 py-3 text-gray-500 text-xs">{{ $booking->created_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            {{-- Legal coverage + chat sessions ------------------------------ --}}
            <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Legal versions in force</h3>
                    @if ($legalVersions->isEmpty())
                        <p class="text-sm text-gray-500">No legal versions seeded — run <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">php artisan db:seed</code>.</p>
                    @else
                        <ul class="space-y-2 text-sm">
                            @foreach ($legalVersions as $version)
                                <li class="flex justify-between items-center">
                                    <span class="text-gray-700 capitalize">{{ str_replace('_', ' ', $version->kind) }}</span>
                                    <span class="text-xs text-gray-500 font-mono">{{ $version->version_label }} · {{ substr($version->content_hash, 0, 8) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-4 text-xs text-gray-500">DRAFT until counsel review. <a href="/legal/versions" class="text-purple-700 hover:underline">JSON</a></p>
                    @endif
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Activity (last 24h)</h3>
                    <ul class="space-y-2 text-sm">
                        <li class="flex justify-between"><span>Chat sessions started</span><span class="font-semibold">{{ $chatSessions24h }}</span></li>
                        <li class="flex justify-between"><span>Tracking events recorded</span><span class="font-semibold">{{ number_format($trackingEvents24h) }}</span></li>
                        <li class="flex justify-between"><span>Suspicious logins</span><span class="font-semibold {{ $suspiciousLogins24h > 0 ? 'text-red-600' : '' }}">{{ $suspiciousLogins24h }}</span></li>
                    </ul>
                </div>
            </section>

        </div>
    </div>
</x-app-layout>
