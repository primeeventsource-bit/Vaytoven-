{{--
    Photo manager for the listing builder.

    Drag-and-drop is progressive: the drop zone is a real <input type="file">
    with a label over it, so it works with no JavaScript, with a keyboard, and
    with a screen reader. The drag handling only adds a second way in.

    Reordering posts an explicit order rather than saving on every drop, so a
    misdrag is undone by not pressing the button.
--}}
<div class="vyt-section" id="photos">
    <h3>Photos</h3>
    <p class="hint">
        {{ $property->photos->count() }} on this listing.
        Large images are resized and converted for the web automatically; the
        original you upload is kept untouched.
    </p>

    @if (! $storageDurable)
        <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">
            <strong>Photo uploads are disabled on this environment.</strong>
            There is no durable storage attached, so an uploaded photo would be lost
            at the next deploy.
        </div>
    @else
        @error('photos')
            <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('admin.properties.photos.store', $property) }}"
              enctype="multipart/form-data" id="vyt-photo-form">
            @csrf
            <div class="vyt-dropzone" id="vyt-dropzone">
                <input type="file" id="vyt-photo-input" name="photos[]" multiple
                       accept="image/jpeg,image/png,image/webp">
                <label for="vyt-photo-input">
                    <strong>Drop photos here, or choose files</strong>
                    <span>JPG, PNG or WebP · up to 30 at a time · 15&nbsp;MB each</span>
                </label>
            </div>

            <div style="display:flex;gap:12px;align-items:flex-end;margin-top:12px;flex-wrap:wrap;">
                <div class="vyt-field" style="max-width:220px;">
                    <label for="photo-category">Add to section</label>
                    <select id="photo-category" name="category">
                        @foreach (\App\Models\PropertyPhoto::CATEGORIES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="vyt-save" style="padding:10px 22px;">Upload</button>
                <span class="vyt-faint" id="vyt-photo-count" style="font-size:13px;"></span>
            </div>
        </form>
    @endif

    @if ($property->photos->isNotEmpty())
        <form method="POST" action="{{ route('admin.properties.photos.reorder', $property) }}"
              id="vyt-reorder-form" style="margin-top:22px;">
            @csrf
            <div class="vyt-photo-grid" id="vyt-photo-grid">
                @foreach ($property->photos as $photo)
                    <div class="vyt-photo-card" draggable="true" data-id="{{ $photo->id }}">
                        <input type="hidden" name="order[]" value="{{ $photo->id }}">

                        <div class="vyt-photo-thumb">
                            @if ($photo->fileExists() || ! $photo->isUploaded())
                                <img src="{{ $photo->displayUrl() }}" alt="{{ $photo->altText() }}" loading="lazy">
                            @else
                                <div class="vyt-photo-missing">File missing from storage</div>
                            @endif

                            @if ($photo->is_cover)
                                <span class="vyt-photo-badge">Cover</span>
                            @endif
                        </div>

                        <div class="vyt-photo-meta">
                            <span>{{ $photo->categoryLabel() }}</span>
                            @if ($photo->width)
                                <span>{{ $photo->width }}×{{ $photo->height }} · {{ $photo->sizeForHumans() }}</span>
                            @endif
                            @if ($photo->uploadedBy)
                                <span class="vyt-faint">{{ $photo->uploadedBy->email }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <button type="submit" class="vyt-save" style="margin-top:14px;padding:9px 20px;font-size:14px;">
                Save photo order
            </button>
        </form>

        <div style="margin-top:22px;border-top:1px solid var(--line);padding-top:18px;">
            <h4 style="margin:0 0 12px;font-size:13px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);">
                Photo details
            </h4>

            @foreach ($property->photos as $photo)
                <div style="display:flex;gap:14px;align-items:flex-start;padding:12px 0;border-top:1px solid var(--line);">
                    <div style="flex:0 0 84px;">
                        @if ($photo->fileExists() || ! $photo->isUploaded())
                            <img src="{{ $photo->displayUrl() }}" alt="" loading="lazy"
                                 style="width:84px;height:64px;object-fit:cover;border-radius:8px;">
                        @endif
                    </div>

                    <form method="POST" action="{{ route('admin.properties.photos.update', [$property, $photo]) }}"
                          style="flex:1;display:grid;gap:8px;grid-template-columns:1fr 1fr auto;align-items:end;">
                        @csrf
                        @method('PATCH')

                        <div class="vyt-field">
                            <label for="cap-{{ $photo->id }}">Caption</label>
                            <input id="cap-{{ $photo->id }}" name="caption" type="text" value="{{ $photo->caption }}">
                        </div>
                        <div class="vyt-field">
                            <label for="alt-{{ $photo->id }}">Alt text</label>
                            <input id="alt-{{ $photo->id }}" name="alt_text" type="text" value="{{ $photo->alt_text }}"
                                   placeholder="What a screen reader announces">
                        </div>
                        <div class="vyt-field" style="min-width:150px;">
                            <label for="cat-{{ $photo->id }}">Section</label>
                            <select id="cat-{{ $photo->id }}" name="category">
                                @foreach (\App\Models\PropertyPhoto::CATEGORIES as $value => $label)
                                    <option value="{{ $value }}" @selected($photo->category === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" style="font-size:13px;color:var(--purple);font-weight:600;">Save</button>
                    </form>

                    <div style="display:flex;gap:10px;align-items:center;">
                        @unless ($photo->is_cover)
                            <form method="POST" action="{{ route('admin.properties.photos.cover', [$property, $photo]) }}">
                                @csrf
                                <button type="submit" style="font-size:13px;">Make cover</button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('admin.properties.photos.destroy', [$property, $photo]) }}"
                              onsubmit="return confirm('Delete this photo? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="font-size:13px;color:#b91c1c;">Delete</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

@push('scripts')
<script>
(function () {
    var input = document.getElementById('vyt-photo-input');
    var zone  = document.getElementById('vyt-dropzone');
    var count = document.getElementById('vyt-photo-count');

    if (input && count) {
        input.addEventListener('change', function () {
            count.textContent = input.files.length ? input.files.length + ' file(s) ready' : '';
        });
    }

    // Drag and drop is additive. The input above already works without it.
    if (zone && input) {
        ['dragenter', 'dragover'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) {
                e.preventDefault();
                zone.classList.add('is-over');
            });
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
    }

    // Reordering. The hidden order[] inputs travel with their card, so moving
    // a card in the DOM is the whole state change - nothing to keep in sync.
    var grid = document.getElementById('vyt-photo-grid');
    if (!grid) { return; }

    var dragged = null;

    grid.addEventListener('dragstart', function (e) {
        dragged = e.target.closest('.vyt-photo-card');
        if (dragged) { dragged.classList.add('is-dragging'); }
    });

    grid.addEventListener('dragend', function () {
        if (dragged) { dragged.classList.remove('is-dragging'); }
        dragged = null;
    });

    grid.addEventListener('dragover', function (e) {
        e.preventDefault();
        var target = e.target.closest('.vyt-photo-card');
        if (!dragged || !target || target === dragged) { return; }

        var cards = Array.prototype.slice.call(grid.children);
        var from = cards.indexOf(dragged);
        var to   = cards.indexOf(target);

        grid.insertBefore(dragged, from < to ? target.nextSibling : target);
    });
})();
</script>
@endpush
