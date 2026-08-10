@extends('dashboard.layout')

@section('eyebrow', 'Admin · Roles')
@section('title', $role->name)

@section('content')
    <section class="vyt-section">
        @include('admin.roles._form')
    </section>
@endsection
