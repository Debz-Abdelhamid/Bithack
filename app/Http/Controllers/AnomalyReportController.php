<?php

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Http\Requests\StoreAnomalyReportRequest;
use App\Models\Equipment;
use App\Models\MaintenanceTicket;
use App\Models\QrCode;
use Illuminate\Http\RedirectResponse;

/**
 * Étape 4 — "Scan QR du bien → ticket créé automatiquement" (Phases.md
 * Phase 6). Grows the Phase 3 public lookup page (QrLookupController)
 * with the submission half; the GET page stays public/unauthenticated
 * (its shipped Phase 3 contract), only this POST requires a session —
 * `auth` middleware + the app's global `redirectGuestsTo` sends a guest
 * to Filament login, same as the rest of the panel.
 *
 * Priority is always `urgent` for this source — the legacy report form
 * (legacy/Patrimo-BitHack .../ReportView.tsx) never offered a category
 * picker and hardcoded every QR-scan report as urgent/48h; matched here
 * rather than inventing a category→priority mapping Schema.md/Phases.md
 * describe but never actually specify (flagged in PROGRESS.md).
 */
class AnomalyReportController extends Controller
{
    public function store(StoreAnomalyReportRequest $request, string $token): RedirectResponse
    {
        $qrCode = QrCode::query()->where('token', $token)->first();

        $equipment = $qrCode?->trackable;

        abort_unless($equipment instanceof Equipment, 404);

        if (MaintenanceTicket::hasActiveTicketFor($equipment->id)) {
            return redirect()
                ->route('qr.lookup', ['token' => $token])
                ->with('anomaly_report_error', __('patrimoine.report.already_reported'));
        }

        $ticket = MaintenanceTicket::create([
            'equipment_id' => $equipment->id,
            'reported_by_user_id' => $request->user()->getKey(),
            'source' => TicketSource::QrScan,
            'description' => $request->validated('description'),
            'priority' => TicketPriority::Urgent,
        ]);

        return redirect()
            ->route('qr.lookup', ['token' => $token])
            ->with('anomaly_report_reference', $ticket->reference);
    }
}
