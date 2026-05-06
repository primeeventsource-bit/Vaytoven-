@props([
    'label' => '',
    'value' => '0',
    'href' => null,
    'tone' => 'slate',
])

@php
    $toneMap = [
        'pink'    => 'bg-pink-50 text-pink-700',
        'purple'  => 'bg-purple-50 text-purple-700',
        'amber'   => 'bg-amber-50 text-amber-800',
        'red'     => 'bg-red-50 text-red-700',
        'emerald' => 'bg-emerald-50 text-emerald-700',
        'blue'    => 'bg-blue-50 text-blue-700',
        'slate'   => 'bg-slate-50 text-slate-700',
    ];
    $accent = $toneMap[$tone] ?? $toneMap['slate'];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => 'block bg-white rounded-lg shadow-sm p-4 hover:shadow transition-shadow']) }}>
        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</div>
        <div class="mt-2 inline-block px-2.5 py-1 rounded-md text-xl font-semibold {{ $accent }}">{{ $value }}</div>
    </a>
@else
    <div {{ $attributes->merge(['class' => 'block bg-white rounded-lg shadow-sm p-4']) }}>
        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</div>
        <div class="mt-2 inline-block px-2.5 py-1 rounded-md text-xl font-semibold {{ $accent }}">{{ $value }}</div>
    </div>
@endif
