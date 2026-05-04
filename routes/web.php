<?php

use App\Http\Controllers\Admin\ContractController as AdminContractController;
use App\Http\Controllers\Client\ContractController as ClientContractController;
use App\Http\Controllers\Webhooks\DocuSignWebhookController;
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

// ---------------------------------------------------------------------------
// DocuSign integration
// ---------------------------------------------------------------------------
//
// Admin contract management — assumes an `admin` middleware that gates on
// staff role. Until that middleware is registered, the `auth` gate alone
// will reject anonymous traffic, but any authenticated user could reach
// these. Add an admin role check before exposing publicly.
Route::middleware(['auth'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('contracts',                  [AdminContractController::class, 'index'])->name('contracts.index');
        Route::get('contracts/create',           [AdminContractController::class, 'create'])->name('contracts.create');
        Route::post('contracts',                 [AdminContractController::class, 'store'])->name('contracts.store');
        Route::get('contracts/{contract}',       [AdminContractController::class, 'show'])->name('contracts.show');
        Route::get('contracts/{contract}/signed.pdf',      [AdminContractController::class, 'downloadSigned'])->name('contracts.download.signed');
        Route::get('contracts/{contract}/certificate.pdf', [AdminContractController::class, 'downloadCertificate'])->name('contracts.download.certificate');
        Route::post('contracts/{contract}/void', [AdminContractController::class, 'void'])->name('contracts.void');
    });

// Client-facing contract dashboard. Mounted at /account/contracts.
Route::middleware(['auth'])
    ->prefix('account')
    ->name('client.')
    ->group(function () {
        Route::get('contracts',                       [ClientContractController::class, 'index'])->name('contracts.index');
        Route::get('contracts/{contract}',            [ClientContractController::class, 'show'])->name('contracts.show');
        Route::get('contracts/{contract}/sign',       [ClientContractController::class, 'sign'])->name('contracts.sign');
        Route::get('contracts/{contract}/signed.pdf', [ClientContractController::class, 'downloadSigned'])->name('contracts.download');
    });

// Inbound DocuSign Connect webhook. CSRF-excluded via bootstrap/app.php;
// authenticated via HMAC signature (WebhookVerifier).
Route::post('/webhooks/docusign', DocuSignWebhookController::class)
    ->name('webhooks.docusign');
