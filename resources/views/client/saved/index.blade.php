@extends('layouts.base')

@section('title', 'Saved advertisements &middot; Vaytoven')
@section('section', 'My account')

@section('content')
    <h1>Saved</h1>
    <p style="color:var(--muted);margin-top:-12px;">
        Advertisements you have saved to come back to. Only you can see this list.
    </p>

    @if ($properties->isEmpty())
        <div class="card">
            <h2 style="margin-top:0;">Nothing saved yet</h2>
            <p style="color:var(--muted);">
                Press <strong>Save</strong> on any advertisement and it will appear here.
            </p>
            <a class="btn btn-primary" href="{{ route('properties.index') }}">Browse advertisements</a>
        </div>
    @else
        <div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));margin-top:20px;">
            @foreach ($properties as $property)
                @php($cover = \App\Models\PropertyPhoto::coverFor($property))
                <div class="card" style="padding:0;overflow:hidden;">
                    <a href="{{ route('properties.show', $property) }}" style="display:block;line-height:0;">
                        @if ($cover && ($cover->fileExists() || ! $cover->isUploaded()))
                            <img src="{{ $cover->displayUrl() }}" alt="{{ $cover->altText() }}" loading="lazy"
                                 style="width:100%;height:170px;object-fit:cover;">
                        @else
                            <div style="width:100%;height:170px;background:var(--line);"></div>
                        @endif
                    </a>

                    <div style="padding:14px 16px 16px;">
                        <a href="{{ route('properties.show', $property) }}"
                           style="font-weight:600;display:block;margin-bottom:4px;">{{ $property->title }}</a>
                        <div style="color:var(--muted);font-size:13px;">
                            {{ $property->city }}{{ $property->region ? ', '.$property->region : '' }}
                        </div>

                        <form method="POST" action="{{ route('saved.toggle', $property) }}" style="margin-top:12px;">
                            @csrf
                            <button type="submit" style="font-size:13px;color:#b91c1c;">Remove</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
