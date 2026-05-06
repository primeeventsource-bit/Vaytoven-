@extends('legal.layout')

@section('eyebrow', 'Action required')
@section('title', 'Updated terms — please review')
@section('effective_date', '—')
@section('version_label', '—')

@section('content')
<p>We've updated the documents below. Please review them and confirm acceptance to continue using your Vaytoven account.</p>

<ul style="background:#fff;border:1px solid var(--line);border-radius:12px;padding:18px 22px;list-style:none;margin:18px 0 24px;">
    @foreach ($missing as $version)
        <li style="padding:8px 0;display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--line);">
            <div>
                <strong style="display:block;">{{ ucwords(str_replace('_', ' ', $version->kind)) }}</strong>
                <span style="font-size:13px;color:var(--muted);">Version {{ $version->version_label }} · effective {{ $version->effective_at?->toFormattedDateString() }}</span>
            </div>
            <a href="{{ $version->content_url }}" target="_blank" rel="noopener" style="font-size:13px;">Read →</a>
        </li>
    @endforeach
</ul>

<form method="POST" action="{{ route('legal.review-and-accept.submit') }}">
    @csrf
    <label style="display:flex;align-items:flex-start;gap:10px;font-size:14px;">
        <input type="checkbox" name="accept" value="1" required style="margin-top:4px;">
        <span>I have read and accept all of the updated documents listed above.</span>
    </label>

    @error('accept')
        <p style="color:#b91c1c;font-size:13px;margin-top:8px;">{{ $message }}</p>
    @enderror

    <button type="submit" style="margin-top:18px;padding:12px 22px;border-radius:999px;border:0;background:var(--gradient);color:#fff;font-weight:600;cursor:pointer;">
        Accept and continue
    </button>
</form>
@endsection
