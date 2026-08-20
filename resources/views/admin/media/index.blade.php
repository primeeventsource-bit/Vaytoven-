@extends('layouts.base')

@section('title', 'Photo library &middot; Vaytoven Admin')
@section('section', 'Admin')

@section('content')
    @include('partials.admin-nav')

    <h1>Photo library</h1>
    <p style="color:var(--muted);margin-top:-12px;max-width:70ch;">
        Stock photography kept in one place and reused. Upload a set once, file it in a folder,
        then add it to any listing in a couple of clicks instead of uploading the same images
        again for every new member.
    </p>

    @if (session('success'))
        <div class="site-alert" style="background:#f0fdf4;border-color:#bbf7d0;color:#166534;">{{ session('success') }}</div>
    @endif
    @error('assets')
        <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">{{ $message }}</div>
    @enderror

    {{-- Folders --------------------------------------------------------------- --}}
    <div class="vyt-card">
        <div class="vyt-card-header">
            <h3>Folders</h3>
            <span class="vyt-section-meta">{{ $collections->count() }}</span>
        </div>

        <div class="vyt-card-body">
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <a href="{{ route('admin.media.index') }}"
                   class="vyt-pill @if($currentSlug === '') is-on @endif"
                   style="text-decoration:none;">All images</a>

                @foreach ($collections as $collection)
                    <a href="{{ route('admin.media.index', ['collection' => $collection->slug]) }}"
                       class="vyt-pill @if($currentSlug === $collection->slug) is-on @endif"
                       style="text-decoration:none;">
                        {{ $collection->name }}
                        <span class="vyt-faint">{{ $collection->assets_count }}</span>
                    </a>
                @endforeach

                {{-- Unsorted is a real place. An upload nobody filed still has
                     to be findable rather than quietly invisible. --}}
                <a href="{{ route('admin.media.index', ['collection' => 'unsorted']) }}"
                   class="vyt-pill @if($currentSlug === 'unsorted') is-on @endif"
                   style="text-decoration:none;">
                    Unsorted <span class="vyt-faint">{{ $unsortedCount }}</span>
                </a>
            </div>

            <form method="POST" action="{{ route('admin.media.folders.store') }}"
                  style="display:flex;gap:10px;align-items:flex-end;margin-top:16px;flex-wrap:wrap;">
                @csrf
                <div class="vyt-field" style="max-width:240px;">
                    <label for="folder-name">New folder</label>
                    <input id="folder-name" name="name" type="text" required placeholder="Pool &amp; exterior shots">
                </div>
                <button type="submit" style="font-size:13px;color:var(--purple);font-weight:600;padding-bottom:10px;">
                    Create folder
                </button>
            </form>

            @if ($current)
                <form method="POST" action="{{ route('admin.media.folders.destroy', $current) }}"
                      style="margin-top:12px;"
                      onsubmit="return confirm('Delete the folder {{ $current->name }}? The images inside move to Unsorted and are not deleted.');">
                    @csrf @method('DELETE')
                    <button type="submit" style="font-size:12.5px;color:#b91c1c;">Delete &ldquo;{{ $current->name }}&rdquo;</button>
                    <span class="vyt-faint" style="font-size:12px;">&mdash; images inside move to Unsorted, nothing is lost</span>
                </form>
            @endif
        </div>
    </div>

    {{-- Upload ---------------------------------------------------------------- --}}
    <div class="vyt-card" style="margin-top:18px;">
        <div class="vyt-card-header"><h3>Add images</h3></div>
        <div class="vyt-card-body">
            @if (! $storageDurable)
                <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;margin:0;">
                    <strong>Uploads are disabled on this environment.</strong>
                    {{ $storageReason }}
                </div>
            @else
                <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="vyt-dropzone" id="vyt-media-dropzone">
                        <input type="file" id="vyt-media-input" name="assets[]" multiple
                               accept="image/jpeg,image/png,image/webp">
                        <label for="vyt-media-input">
                            <strong>Drop images here, or choose files</strong>
                            <span>JPG, PNG or WebP &middot; up to 60 at a time &middot; 15&nbsp;MB each</span>
                        </label>
                    </div>

                    <div style="display:flex;gap:12px;align-items:flex-end;margin-top:12px;flex-wrap:wrap;">
                        <div class="vyt-field" style="max-width:240px;">
                            <label for="upload-folder">File into</label>
                            <select id="upload-folder" name="collection">
                                <option value="">Unsorted</option>
                                @foreach ($collections as $collection)
                                    <option value="{{ $collection->id }}" @selected($current && $current->id === $collection->id)>
                                        {{ $collection->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="vyt-save" style="padding:10px 22px;">Upload</button>
                        <span class="vyt-faint" id="vyt-media-count" style="font-size:13px;"></span>
                    </div>
                </form>

                <p class="site-note" style="margin-top:12px;">
                    Images are resized and converted for the web automatically, and the original you
                    upload is kept. Uploading the same image twice into one folder reuses the copy
                    already there instead of making a duplicate.
                </p>
            @endif
        </div>
    </div>

    {{-- The images ------------------------------------------------------------ --}}
    <div class="vyt-card" style="margin-top:18px;">
        <div class="vyt-card-header">
            <h3>{{ $current?->name ?? ($currentSlug === 'unsorted' ? 'Unsorted' : 'All images') }}</h3>
            <span class="vyt-section-meta">{{ $assets->total() }} image(s)</span>
        </div>

        @if ($assets->isEmpty())
            <div class="vyt-card-empty">
                Nothing here yet. Upload images above and they will appear for use on any listing.
            </div>
        @else
            <div class="vyt-card-body">
                <div class="vyt-media-grid">
                    @foreach ($assets as $asset)
                        <figure class="vyt-media-tile">
                            @if ($asset->fileExists())
                                <img src="{{ $asset->displayUrl() }}" alt="{{ $asset->altText() }}" loading="lazy">
                            @else
                                {{-- A row whose file has gone is worse than no row: it looks
                                     usable and fails when someone puts it on a listing. --}}
                                <div class="vyt-media-missing">File missing from storage</div>
                            @endif

                            <figcaption>
                                <form method="POST" action="{{ route('admin.media.update', $asset) }}">
                                    @csrf @method('PATCH')
                                    <input type="text" name="label" value="{{ $asset->label }}"
                                           placeholder="{{ $asset->original_name ?: 'Label' }}" aria-label="Label">
                                    <select name="collection" aria-label="Folder">
                                        <option value="">Unsorted</option>
                                        @foreach ($collections as $collection)
                                            <option value="{{ $collection->id }}" @selected($asset->media_collection_id === $collection->id)>
                                                {{ $collection->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit">Save</button>
                                </form>

                                <div class="vyt-media-meta">
                                    <span>{{ $asset->width }}&times;{{ $asset->height }} &middot; {{ $asset->sizeForHumans() }}</span>
                                    <form method="POST" action="{{ route('admin.media.destroy', $asset) }}"
                                          onsubmit="return confirm('Remove this image from the library? Listings already using it keep their own copy.');">
                                        @csrf @method('DELETE')
                                        <button type="submit">Delete</button>
                                    </form>
                                </div>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>

                <div style="margin-top:16px;">{{ $assets->links() }}</div>
            </div>
        @endif
    </div>
@endsection

@push('styles')
<style>
    .vyt-pill {
        display:inline-flex; align-items:center; gap:6px;
        padding:7px 14px; border:1px solid var(--line); border-radius:999px;
        font-size:13px; color:inherit; background:#fff;
    }
    .vyt-pill.is-on { border-color:var(--purple); color:var(--purple); background:rgba(123,44,191,.06); font-weight:600; }

    .vyt-media-grid {
        display:grid; gap:14px;
        grid-template-columns:repeat(auto-fill, minmax(190px, 1fr));
    }
    .vyt-media-tile {
        margin:0; border:1px solid var(--line); border-radius:12px; overflow:hidden; background:#fff;
    }
    .vyt-media-tile img { width:100%; height:130px; object-fit:cover; display:block; }
    .vyt-media-missing {
        height:130px; display:flex; align-items:center; justify-content:center;
        background:#fef2f2; color:#991b1b; font-size:12px; text-align:center; padding:8px;
    }
    .vyt-media-tile figcaption { padding:10px; display:grid; gap:8px; }
    .vyt-media-tile input[type=text], .vyt-media-tile select {
        width:100%; font-size:12.5px; padding:6px 8px;
        border:1px solid var(--line); border-radius:7px;
    }
    .vyt-media-tile figcaption > form { display:grid; gap:6px; }
    .vyt-media-tile figcaption button { font-size:12.5px; color:var(--purple); font-weight:600; }
    .vyt-media-meta {
        display:flex; align-items:center; justify-content:space-between; gap:8px;
        font-size:11.5px; color:var(--muted);
    }
    .vyt-media-meta button { color:#b91c1c; font-weight:500; }
</style>
@endpush

@push('scripts')
<script>
// Drag and drop is additive: the file input above already works on its own,
// with a keyboard and with a screen reader. This only adds a second way in.
(function () {
    var input = document.getElementById('vyt-media-input');
    var zone  = document.getElementById('vyt-media-dropzone');
    var count = document.getElementById('vyt-media-count');
    if (!input || !zone) { return; }

    input.addEventListener('change', function () {
        if (count) { count.textContent = input.files.length ? input.files.length + ' file(s) ready' : ''; }
    });

    ['dragenter', 'dragover'].forEach(function (evt) {
        zone.addEventListener(evt, function (e) { e.preventDefault(); zone.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (evt) {
        zone.addEventListener(evt, function () { zone.classList.remove('is-over'); });
    });
    zone.addEventListener('drop', function (e) {
        e.preventDefault();
        if (e.dataTransfer && e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event('change'));
        }
    });
})();
</script>
@endpush
