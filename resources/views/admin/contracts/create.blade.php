@extends('layouts.base')

@section('title', 'Send a contract &middot; Vaytoven Admin')
@section('section', 'Admin')
@section('nav')
    <a href="{{ route('admin.contracts.index') }}">Contracts</a>
    <a href="{{ route('admin.contracts.create') }}" class="active">Send new</a>
@endsection

@section('content')
    <h1>Send a contract</h1>

    <form method="post" action="{{ route('admin.contracts.store') }}" enctype="multipart/form-data" class="card" style="max-width:760px;">
        @csrf

        <div class="form-row">
            <div class="form-field">
                <label for="client_name">Client name</label>
                <input id="client_name" name="client_name" type="text" required value="{{ old('client_name') }}">
            </div>
            <div class="form-field">
                <label for="client_email">Client email</label>
                <input id="client_email" name="client_email" type="email" required value="{{ old('client_email') }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label for="client_phone">Client phone (optional)</label>
                <input id="client_phone" name="client_phone" type="tel" value="{{ old('client_phone') }}">
            </div>
            <div class="form-field">
                <label for="user_id">User ID (optional, links to client account)</label>
                <input id="user_id" name="user_id" type="number" value="{{ old('user_id') }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label for="contract_type">Contract type</label>
                <select id="contract_type" name="contract_type" required>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('contract_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="title">Display title</label>
                <input id="title" name="title" type="text" required placeholder="e.g. Host Listing Agreement v2" value="{{ old('title') }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label for="template_id">DocuSign template ID (optional)</label>
                <input id="template_id" name="template_id" type="text" placeholder="leave blank to upload a PDF" value="{{ old('template_id') }}">
            </div>
            <div class="form-field">
                <label for="payment_id">Linked payment / invoice ID (optional)</label>
                <input id="payment_id" name="payment_id" type="text" value="{{ old('payment_id') }}">
            </div>
        </div>

        <div class="form-field" style="margin-bottom:14px;">
            <label for="pdf">Upload PDF (only if no template ID)</label>
            <input id="pdf" name="pdf" type="file" accept="application/pdf">
        </div>

        <div style="display:flex; gap:12px; margin-top:8px;">
            <button type="submit" class="btn btn-primary">Send to client</button>
            <a href="{{ route('admin.contracts.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
@endsection
