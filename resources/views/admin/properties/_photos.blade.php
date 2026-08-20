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

        {{-- Add from the shared library.

             The reason the library exists: a new member's listing gets its
             stock set in one action rather than the same images being uploaded
             again per property. Selected images are COPIED onto this listing,
             so the listing owns them and nothing here changes if somebody
             reorganizes the library later. --}}
        @if (($libraryAssets ?? collect())->isNotEmpty())
            <details class="vyt-library-picker" style="margin-top:18px;">
                <summary style="font-size:13px;cursor:pointer;color:var(--purple);font-weight:600;">
                    Add from photo library ({{ $libraryAssets->count() }} available)
                </summary>

                <form method="POST" action="{{ route('admin.properties.photos.from-library', $property) }}"
                      style="margin-top:12px;">
                    @csrf

                    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px;">
                        <div class="vyt-field" style="max-width:220px;">
                            <label for="library-category">Add to section</label>
                            <select id="library-category" name="category">
                                @foreach (\App\Models\PropertyPhoto::CATEGORIES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="vyt-save" style="padding:9px 20px;font-size:14px;">
                            Add selected
                        </button>
                        <a href="{{ route('admin.media.index') }}" style="font-size:13px;padding-bottom:10px;">
                            Manage library &rarr;
                        </a>
                    </div>

                    <div class="vyt-lib-grid">
                        @foreach ($libraryAssets as $asset)
                            <label class="vyt-lib-tile">
                                <input type="checkbox" name="assets[]" value="{{ $asset->id }}">
                                <img src="{{ $asset->displayUrl() }}" alt="{{ $asset->altText() }}" loading="lazy">
                                <span>{{ $asset->label ?: ($asset->collection?->name ?? 'Unsorted') }}</span>
                            </label>
                        @endforeach
                    </div>
                </form>
            </details>
        @endif
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

            @error('photo_transform')
                <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">{{ $message }}</div>
            @enderror

            @foreach ($property->photos as $photo)
              <div style="border-top:1px solid var(--line);">
                <div style="display:flex;gap:14px;align-items:flex-start;padding:12px 0;">
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

                @if ($photo->isUploaded())
                    {{--
                        Rotate and crop.

                        A <details> rather than a modal: the editor is used on a
                        handful of photos out of a gallery, and a closed summary
                        keeps the list scannable while still being one click and
                        no JavaScript away.

                        Rotating clears the crop, which is why the two are
                        separate forms. A box drawn on an upright photo selects a
                        different part of the same photo once it is on its side,
                        so carrying it through a turn would quietly crop
                        somewhere nobody chose. Starting the box again is a
                        second drag; noticing the wrong crop went live is not.
                    --}}
                    <details class="vyt-photo-editor" style="margin:0 0 12px 98px;">
                        <summary style="font-size:13px;cursor:pointer;color:var(--purple);font-weight:600;">
                            Rotate &amp; crop
                            @if ($photo->isEdited())
                                <span class="vyt-faint" style="font-weight:400;">
                                    — edited{{ (int) $photo->rotation ? ', rotated '.$photo->rotation.'°' : '' }}{{ $photo->cropBox() ? ', cropped' : '' }}
                                </span>
                            @endif
                        </summary>

                        @unless ($photo->originalExists())
                            <p class="site-note" style="margin:10px 0 0;color:#991b1b;">
                                The pristine original for this photo is no longer in storage, so it
                                cannot be rotated or cropped — every edit is replayed against the
                                original rather than against the copy on the page. Re-upload it to
                                get the editor back.
                            </p>
                        @else
                            <div style="display:flex;gap:20px;align-items:flex-start;margin-top:12px;flex-wrap:wrap;">
                                <div style="flex:1 1 320px;min-width:260px;">
                                    <div class="vyt-crop-stage" data-photo="{{ $photo->id }}"
                                         style="position:relative;display:inline-block;max-width:100%;line-height:0;
                                                border-radius:10px;overflow:hidden;background:#111;touch-action:none;">
                                        <img src="{{ $photo->displayUrl() }}" alt="{{ $photo->altText() }}"
                                             draggable="false"
                                             style="max-width:100%;max-height:320px;display:block;user-select:none;">
                                        <div class="vyt-crop-box" hidden
                                             style="position:absolute;border:2px solid #fff;box-shadow:0 0 0 9999px rgba(0,0,0,.45);
                                                    pointer-events:none;"></div>
                                    </div>
                                    <p class="vyt-faint" style="font-size:12px;margin:6px 0 0;">
                                        Drag on the image to choose a crop, or type the values below.
                                        Numbers are fractions of the image: 0 is the left/top edge, 1 the full width/height.
                                    </p>
                                </div>

                                <div style="flex:0 0 250px;display:flex;flex-direction:column;gap:14px;">
                                    {{-- Rotation. Each button submits where the photo should END UP,
                                         not how far to turn, so a double-click or a resubmitted POST
                                         lands in the same place rather than turning twice. --}}
                                    <form method="POST" action="{{ route('admin.properties.photos.transform', [$property, $photo]) }}">
                                        @csrf
                                        <div class="vyt-faint" style="font-size:12px;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">
                                            Rotate
                                        </div>
                                        <div style="display:flex;gap:8px;">
                                            <button type="submit" name="rotation" value="{{ ((int) $photo->rotation + 270) % 360 }}"
                                                    style="font-size:13px;padding:7px 12px;border:1px solid var(--line);border-radius:8px;">
                                                ↺ Left
                                            </button>
                                            <button type="submit" name="rotation" value="{{ ((int) $photo->rotation + 90) % 360 }}"
                                                    style="font-size:13px;padding:7px 12px;border:1px solid var(--line);border-radius:8px;">
                                                ↻ Right
                                            </button>
                                        </div>
                                        <p class="vyt-faint" style="font-size:11.5px;margin:6px 0 0;">
                                            Rotating clears any crop, because the box would no longer
                                            cover the same part of the photo.
                                        </p>
                                    </form>

                                    <form method="POST" action="{{ route('admin.properties.photos.transform', [$property, $photo]) }}"
                                          class="vyt-crop-form" data-photo="{{ $photo->id }}">
                                        @csrf
                                        <input type="hidden" name="rotation" value="{{ (int) $photo->rotation }}">

                                        <div class="vyt-faint" style="font-size:12px;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">
                                            Crop
                                        </div>

                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                                            @foreach ([['crop_x', 'Left'], ['crop_y', 'Top'], ['crop_w', 'Width'], ['crop_h', 'Height']] as [$field, $label])
                                                <div class="vyt-field">
                                                    <label for="{{ $field }}-{{ $photo->id }}" style="font-size:11px;">{{ $label }}</label>
                                                    <input id="{{ $field }}-{{ $photo->id }}" name="{{ $field }}"
                                                           type="number" step="0.001" min="0" max="1"
                                                           value="{{ $photo->cropBox()[str_replace('crop_', '', $field)] ?? '' }}"
                                                           style="font-size:13px;">
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="vyt-field" style="margin-top:8px;">
                                            <label for="ratio-{{ $photo->id }}" style="font-size:11px;">Lock ratio</label>
                                            <select id="ratio-{{ $photo->id }}" class="vyt-crop-ratio" style="font-size:13px;">
                                                <option value="">Free</option>
                                                <option value="1.7778">16:9 — wide</option>
                                                <option value="1.5">3:2</option>
                                                <option value="1.3333">4:3</option>
                                                <option value="1">1:1 — square</option>
                                            </select>
                                        </div>

                                        <div style="display:flex;gap:10px;align-items:center;margin-top:10px;">
                                            <button type="submit" class="vyt-save" style="font-size:13px;padding:8px 18px;">Apply crop</button>
                                            <button type="button" class="vyt-crop-clear" style="font-size:13px;">Clear box</button>
                                        </div>
                                    </form>

                                    @if ($photo->isEdited())
                                        <form method="POST" action="{{ route('admin.properties.photos.transform', [$property, $photo]) }}">
                                            @csrf
                                            <input type="hidden" name="rotation" value="0">
                                            <button type="submit" style="font-size:13px;">
                                                Reset to original
                                            </button>
                                            <p class="vyt-faint" style="font-size:11.5px;margin:4px 0 0;">
                                                Undoes every edit. The upload itself is never altered, so
                                                this always gets the full photo back.
                                            </p>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endunless
                    </details>
                @endif
              </div>
            @endforeach
        </div>
    @endif
</div>

@push('styles')
<style>
    .vyt-lib-grid { display:grid; gap:10px; grid-template-columns:repeat(auto-fill, minmax(140px,1fr)); max-height:420px; overflow-y:auto; padding:4px; }
    .vyt-lib-tile { position:relative; border:2px solid var(--line); border-radius:10px; overflow:hidden; cursor:pointer; background:#fff; display:block; }
    .vyt-lib-tile img { width:100%; height:96px; object-fit:cover; display:block; }
    .vyt-lib-tile span { display:block; padding:6px 8px; font-size:11.5px; color:var(--muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .vyt-lib-tile input { position:absolute; top:8px; left:8px; z-index:2; width:18px; height:18px; }
    .vyt-lib-tile:has(input:checked) { border-color:var(--purple); box-shadow:0 0 0 3px rgba(123,44,191,.15); }
</style>
@endpush

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

// Drag-to-crop.
//
// Additive, like everything else here: the four number inputs are the real
// control and they submit on their own. This only lets someone draw the box
// instead of computing it, and paints back whatever is already saved so an
// existing crop is visible rather than merely numeric.
(function () {
    document.querySelectorAll('.vyt-crop-stage').forEach(function (stage) {
        var id    = stage.getAttribute('data-photo');
        var form  = document.querySelector('.vyt-crop-form[data-photo="' + id + '"]');
        var box   = stage.querySelector('.vyt-crop-box');
        var img   = stage.querySelector('img');
        if (!form || !box || !img) { return; }

        var fields = {
            x: form.querySelector('[name="crop_x"]'),
            y: form.querySelector('[name="crop_y"]'),
            w: form.querySelector('[name="crop_w"]'),
            h: form.querySelector('[name="crop_h"]')
        };
        var ratioSelect = form.querySelector('.vyt-crop-ratio');
        var clear       = form.querySelector('.vyt-crop-clear');

        function paint() {
            var x = parseFloat(fields.x.value), y = parseFloat(fields.y.value);
            var w = parseFloat(fields.w.value), h = parseFloat(fields.h.value);

            if ([x, y, w, h].some(isNaN) || w <= 0 || h <= 0) {
                box.hidden = true;
                return;
            }

            box.hidden = false;
            box.style.left   = (x * 100) + '%';
            box.style.top    = (y * 100) + '%';
            box.style.width  = (w * 100) + '%';
            box.style.height = (h * 100) + '%';
        }

        function round(n) { return Math.round(n * 1000) / 1000; }

        function write(x, y, w, h) {
            fields.x.value = round(x);
            fields.y.value = round(y);
            fields.w.value = round(w);
            fields.h.value = round(h);
            paint();
        }

        // Fraction of the displayed image, clamped, so a drag that leaves the
        // element does not produce a box outside the photo.
        function point(e) {
            var r = img.getBoundingClientRect();
            return {
                x: Math.min(Math.max((e.clientX - r.left) / r.width, 0), 1),
                y: Math.min(Math.max((e.clientY - r.top) / r.height, 0), 1)
            };
        }

        var start = null;

        stage.addEventListener('pointerdown', function (e) {
            if (e.button !== 0) { return; }
            e.preventDefault();
            start = point(e);
            stage.setPointerCapture(e.pointerId);
        });

        stage.addEventListener('pointermove', function (e) {
            if (!start) { return; }

            var now = point(e);
            var x = Math.min(start.x, now.x), y = Math.min(start.y, now.y);
            var w = Math.abs(now.x - start.x), h = Math.abs(now.y - start.y);

            // A locked ratio is in pixels, not fractions: 1:1 on a 2:1 photo is
            // half its width, not half of each side.
            var ratio = parseFloat(ratioSelect && ratioSelect.value);
            if (!isNaN(ratio) && ratio > 0) {
                var r = img.getBoundingClientRect();
                h = (w * r.width) / ratio / r.height;
                if (y + h > 1) { h = 1 - y; w = (h * r.height * ratio) / r.width; }
            }

            write(x, y, Math.min(w, 1 - x), Math.min(h, 1 - y));
        });

        ['pointerup', 'pointercancel'].forEach(function (evt) {
            stage.addEventListener(evt, function () { start = null; });
        });

        Object.values(fields).forEach(function (input) {
            input.addEventListener('input', paint);
        });

        if (clear) {
            clear.addEventListener('click', function () {
                Object.values(fields).forEach(function (input) { input.value = ''; });
                paint();
            });
        }

        paint();
    });
})();
</script>
@endpush
