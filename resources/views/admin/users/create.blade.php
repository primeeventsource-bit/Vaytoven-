@extends('dashboard.layout')

@section('eyebrow', 'Admin · Users')
@section('title', 'Create user')

@section('content')

    <div style="max-width:680px;">
        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>New user</h3>
                <span class="vyt-section-meta">An invitation email is NOT sent — share the password securely</span>
            </div>
            <div class="vyt-card-body">
                <form method="POST" action="{{ route('admin.users.store') }}">
                    @csrf
                    @include('admin.users._form', [
                        'user' => null,
                        'roles' => $roles,
                        'canGrantAdmin' => $canGrantAdmin,
                        'isSelf' => false,
                    ])
                </form>
            </div>
        </div>
    </div>

@endsection
