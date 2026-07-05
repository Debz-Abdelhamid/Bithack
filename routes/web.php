<?php

use App\Http\Controllers\EquipmentLabelController;
use App\Http\Controllers\QrLookupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Public, read-only QR lookup — what a printed label resolves to
// (Phases.md Phase 3). Heavily rate limited per Security.md §5 (per-IP +
// per-token limiter defined in AppServiceProvider); the UUID constraint
// rejects junk before it ever reaches the database.
Route::get('/report/{token}', QrLookupController::class)
    ->whereUuid('token')
    ->middleware('throttle:qr-lookup')
    ->name('qr.lookup');

// Printable A4 label — authenticated (panel session), policy-checked in
// the controller, throttled like the rest of the panel.
Route::get('/equipments/{equipment}/label', EquipmentLabelController::class)
    ->middleware(['auth', 'throttle:panel'])
    ->name('equipments.label');
