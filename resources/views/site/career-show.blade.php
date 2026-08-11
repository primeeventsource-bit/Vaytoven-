@extends('layouts.site')

@section('title', $opening->title)
@section('meta_description', $opening->summary)

@section('content')
<div class="site-shell">
    <section class="site-hero">
        <p class="eyebrow"><a href="{{ route('careers.index') }}">Careers</a> · {{ $opening->department }}</p>
        <h1>{{ $opening->title }}</h1>
        <p class="lede">{{ $opening->summary }}</p>
        <p style="margin-top:14px; font-size:13.5px; color:var(--muted);">
            {{ $opening->location }} · {{ $opening->employmentTypeLabel() }}
        </p>
    </section>

    <section class="site-section">
        <div class="site-grid cols-2" style="align-items:start; gap:32px;">
            <div>
                <h2>About the role</h2>
                <div style="color:var(--muted); line-height:1.7;">{!! nl2br(e($opening->description)) !!}</div>

                @if ($opening->requirements)
                    <h2 style="margin-top:30px;">What we're looking for</h2>
                    <div style="color:var(--muted); line-height:1.7;">{!! nl2br(e($opening->requirements)) !!}</div>
                @endif
            </div>

            <div>
                <div class="site-card" id="apply">
                    <h3>Apply</h3>

                    @if (session('application_success'))
                        <div class="site-alert" style="margin:12px 0 0;">
                            <strong>{{ session('application_success') }}</strong>
                            Reference <code>{{ session('application_reference') }}</code>.
                        </div>
                    @else
                        <p style="margin:0 0 16px;">A CV and a short note about why this role is enough.</p>

                        <form method="POST" action="{{ route('careers.apply', $opening) }}" enctype="multipart/form-data">
                            @csrf

                            <div class="site-row-2">
                                <div class="site-field">
                                    <label for="first_name">First name</label>
                                    <input id="first_name" name="first_name" type="text"
                                           value="{{ old('first_name') }}" required autocomplete="given-name">
                                    @error('first_name') <div class="err">{{ $message }}</div> @enderror
                                </div>
                                <div class="site-field">
                                    <label for="last_name">Last name</label>
                                    <input id="last_name" name="last_name" type="text"
                                           value="{{ old('last_name') }}" required autocomplete="family-name">
                                    @error('last_name') <div class="err">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="site-field">
                                <label for="email">Email</label>
                                <input id="email" name="email" type="email"
                                       value="{{ old('email') }}" required autocomplete="email">
                                @error('email') <div class="err">{{ $message }}</div> @enderror
                            </div>

                            <div class="site-field">
                                <label for="phone">Phone <span style="text-transform:none; letter-spacing:0;">(optional)</span></label>
                                <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel">
                                @error('phone') <div class="err">{{ $message }}</div> @enderror
                            </div>

                            <div class="site-field">
                                <label for="resume">CV / résumé</label>
                                <input id="resume" name="resume" type="file" accept=".pdf,.doc,.docx">
                                <p style="font-size:12px; color:var(--muted); margin:6px 0 0;">PDF or Word, up to 5 MB.</p>
                                @error('resume') <div class="err">{{ $message }}</div> @enderror
                            </div>

                            <div class="site-field">
                                <label for="cover_note">Why this role? <span style="text-transform:none; letter-spacing:0;">(optional)</span></label>
                                <textarea id="cover_note" name="cover_note" style="min-height:110px;">{{ old('cover_note') }}</textarea>
                                @error('cover_note') <div class="err">{{ $message }}</div> @enderror
                            </div>

                            <button type="submit" class="site-cta" data-track-audience="host"
                                    data-track-cta="career_apply">Send application</button>
                        </form>
                    @endif
                </div>

                <p class="site-note">
                    Vaytoven Technologies LLC is an equal opportunity employer. We consider all
                    applicants without regard to race, colour, religion, sex, sexual orientation,
                    gender identity, national origin, age, disability or veteran status.
                </p>
            </div>
        </div>
    </section>
</div>
@endsection
