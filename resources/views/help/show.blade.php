@extends('help.layout')

@section('title', $article->title)

@section('content')
    <a href="{{ route('help.index') }}" class="help-article-back">← Back to help center</a>
    <article class="help-article">
        <div class="help-article-meta">{{ $article->audience->label() }} · {{ ucfirst(str_replace('-', ' ', $article->category)) }}</div>
        <h1>{{ $article->title }}</h1>
        <p class="help-article-summary">{{ $article->summary }}</p>
        <div class="help-article-body">
            @foreach (preg_split('/\R\R+/', $article->body) as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
    </article>
@endsection
