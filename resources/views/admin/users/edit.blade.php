@extends('dashboard.layout')

@section('eyebrow', 'Admin · Users')
@section('title', 'Edit ' . $user->name)

@section('content')

    <div style="max-width:680px;">
        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>Edit {{ $user->email }}</h3>
                <a href="{{ route('admin.users.show', $user) }}" class="vyt-faint" style="font-size:13px;">← Back</a>
            </div>
            <div class="vyt-card-body">
                <form method="POST" action="{{ route('admin.users.update', $user) }}">
                    @csrf
                    @method('PATCH')
                    @include('admin.users._form', [
                        'user' => $user,
                        'roles' => $roles,
                        'canGrantAdmin' => $canGrantAdmin,
                        'isSelf' => $isSelf,
                    ])
                </form>
            </div>
        </div>
    </div>

@endsection
