<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Welcome back, {{ $me->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            <section class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-admin-tile label="Upcoming stays" :value="$upcomingCount" tone="purple" />
                <x-admin-tile label="Bookings" :value="$bookings->count()" tone="pink" />
                <x-admin-tile label="Recent charges" :value="$charges->count()" tone="emerald" />
            </section>

            <section class="bg-white rounded-lg shadow-sm">
                <header class="flex items-center justify-between px-6 py-4 border-b">
                    <h3 class="font-semibold text-gray-900">My bookings</h3>
                    <span class="text-xs text-gray-500">{{ $bookings->count() }} total · last 10</span>
                </header>
                @if ($bookings->isEmpty())
                    <p class="px-6 py-8 text-sm text-gray-500">No bookings yet — <a href="/" class="text-purple-700 hover:underline">browse properties</a> to plan a stay.</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="text-left px-6 py-2 font-medium">Code</th>
                                <th class="text-left px-6 py-2 font-medium">Property</th>
                                <th class="text-left px-6 py-2 font-medium">Dates</th>
                                <th class="text-left px-6 py-2 font-medium">Status</th>
                                <th class="text-left px-6 py-2 font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($bookings as $booking)
                                <tr>
                                    <td class="px-6 py-3 font-mono text-xs text-purple-700">{{ $booking->confirmation_code }}</td>
                                    <td class="px-6 py-3">{{ $booking->property?->title ?? '—' }}</td>
                                    <td class="px-6 py-3 text-gray-600 text-xs">
                                        {{ $booking->check_in_date?->format('M j') }} – {{ $booking->check_out_date?->format('M j, Y') }}
                                    </td>
                                    <td class="px-6 py-3"><span class="text-xs px-2 py-0.5 rounded bg-gray-100">{{ $booking->status->value }}</span></td>
                                    <td class="px-6 py-3">${{ number_format($booking->total_cents / 100, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            <section class="bg-white rounded-lg shadow-sm">
                <header class="flex items-center justify-between px-6 py-4 border-b">
                    <h3 class="font-semibold text-gray-900">Recent charges</h3>
                    <span class="text-xs text-gray-500">Latest 5</span>
                </header>
                @if ($charges->isEmpty())
                    <p class="px-6 py-8 text-sm text-gray-500">No charges on your account.</p>
                @else
                    <ul class="divide-y">
                        @foreach ($charges as $charge)
                            <li class="px-6 py-3 flex justify-between text-sm">
                                <span class="text-gray-600 text-xs">{{ $charge->created_at?->format('M j, Y') }}</span>
                                <span class="font-mono text-xs">{{ strtoupper($charge->currency ?? 'USD') }} ${{ number_format($charge->amount_cents / 100, 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Quick links</h3>
                <div class="flex flex-wrap gap-3 text-sm">
                    <a href="/help" class="px-3 py-1.5 rounded-full bg-purple-50 text-purple-700 hover:bg-purple-100">Help center</a>
                    <a href="{{ route('profile.edit') }}" class="px-3 py-1.5 rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200">Profile</a>
                    <a href="{{ route('legal.tos') }}" class="px-3 py-1.5 rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200">Terms</a>
                    <a href="{{ route('legal.privacy') }}" class="px-3 py-1.5 rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200">Privacy</a>
                </div>
            </section>

        </div>
    </div>
</x-app-layout>
