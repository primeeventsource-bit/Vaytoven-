@extends('dashboard.layout')

@section('eyebrow', 'Admin · Roles')
@section('title', 'New role')

@section('content')
    <section class="vyt-section">
        @include('admin.roles._form')
    </section>
@endsection
