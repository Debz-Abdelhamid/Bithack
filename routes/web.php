<?php

use App\Http\Controllers\AnomalyReportController;
use App\Http\Controllers\EquipmentLabelController;
use App\Http\Controllers\LocalEquipmentListController;
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

// Anomaly-report submission (Phases.md Phase 6) — same URL family, but
// requires a session (redirectGuestsTo sends a guest to Filament login,
// bootstrap/app.php) and its own tighter limiter (Security.md §5 flags
// this as the single most abuse-prone endpoint in the module).
Route::post('/report/{token}', [AnomalyReportController::class, 'store'])
    ->whereUuid('token')
    ->middleware(['auth', 'throttle:anomaly-report'])
    ->name('report.store');

// Printable A4 label — authenticated (panel session), policy-checked in
// the controller, throttled like the rest of the panel.
Route::get('/equipments/{equipment}/label', EquipmentLabelController::class)
    ->middleware(['auth', 'throttle:panel'])
    ->name('equipments.label');

// Printable A4 room-equipment inventory list (owner request, 2026-07-08) —
// same shape as the equipment label above.
Route::get('/locals/{local}/equipment-list', LocalEquipmentListController::class)
    ->middleware(['auth', 'throttle:panel'])
    ->name('locals.equipment-list');
