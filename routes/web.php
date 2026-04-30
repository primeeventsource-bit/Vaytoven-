<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::post('/members/enquiry', function (Request $request) {
    $data = $request->validate([
        'first_name'      => ['required', 'string', 'max:80'],
        'last_name'       => ['required', 'string', 'max:80'],
        'email'           => ['required', 'email', 'max:160'],
        'phone'           => ['required', 'string', 'max:40'],
        'club'            => ['required', 'string', 'max:80'],
        'property'        => ['required', 'string', 'max:255'],
        'points'          => ['required', 'string', 'max:60'],
        'contact_window'  => ['nullable', 'string', 'max:120'],
        'consent'         => ['accepted'],
    ]);

    Log::channel('single')->info('members_enquiry', [
        'data' => $data,
        'ip'   => $request->ip(),
        'ua'   => substr((string) $request->userAgent(), 0, 240),
    ]);

    return response()->json(['ok' => true]);
})->name('members.enquiry');
