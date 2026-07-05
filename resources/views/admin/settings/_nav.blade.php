@php
    $activeGroup = $activeGroup ?? null;
    $activeScreen = $activeScreen ?? null;
@endphp

<nav class="vyt-setnav" aria-label="Settings sections">
    @foreach (\App\Services\Settings\SettingsSchema::GROUPS as $g => $label)
        <a href="{{ route('admin.settings.group', $g) }}" @class(['active' => $activeGroup === $g])>{{ $label }}</a>
    @endforeach
    <span class="vyt-setnav-sep"></span>
    <a href="{{ route('admin.settings.flags') }}" @class(['active' => $activeScreen === 'flags'])>Feature Flags</a>
    <a href="{{ route('admin.settings.processors') }}" @class(['active' => $activeScreen === 'processors'])>Payment Processors</a>
    <a href="{{ route('admin.settings.templates') }}" @class(['active' => $activeScreen === 'templates'])>Templates</a>
    @foreach (\App\Http\Controllers\Admin\ConfigCollectionController::COLLECTIONS as $slug => $config)
        <a href="{{ route('admin.settings.collections.index', $slug) }}" @class(['active' => $activeScreen === 'collection:'.$slug])>{{ $config['title'] }}</a>
    @endforeach
</nav>
