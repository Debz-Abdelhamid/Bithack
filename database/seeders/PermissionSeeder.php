<?php

namespace Database\Seeders;

use App\Support\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Baseline grants straight from the Security.md §3 matrix — everything else
 * stays at zero and is adjusted from the Shield UI. Idempotent: permissions
 * are findOrCreate'd (same names shield:generate produces) so fresh installs
 * and tests do not depend on running the generator first.
 */
class PermissionSeeder extends Seeder
{
    private const RESOURCE_METHODS = [
        'ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny',
        'Restore', 'RestoreAny', 'ForceDelete', 'ForceDeleteAny',
        'Replicate', 'Reorder',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $fullPatrimoine = [];

        foreach (['Building', 'Local', 'Equipment', 'PurchaseReference', 'Assignment', 'RoomReservation', 'Department', 'AcademicTerm'] as $model) {
            foreach (self::RESOURCE_METHODS as $method) {
                $fullPatrimoine[] = Permission::findOrCreate("{$method}:{$model}", 'web')->name;
            }
        }

        $campusMap = Permission::findOrCreate('View:CampusMap', 'web')->name;

        // Read-only weekly availability grid (Phase 5) — same "public
        // campus" tier as the map, granted independently of the admin
        // RoomReservation resource below.
        $reservationAvailability = Permission::findOrCreate('View:ReservationAvailability', 'web')->name;

        // Operational label printing (marks the QR as printed) — its own
        // permission so it stays delegable without write access.
        $printLabel = Permission::findOrCreate('PrintLabel:Equipment', 'web')->name;

        // Phase 5 reservation abilities beyond the standard CRUD set —
        // faculty-authored timetable entry and ad-hoc request handling.
        $manageTimetable = Permission::findOrCreate('ManageTimetable:RoomReservation', 'web')->name;
        $approveReservation = Permission::findOrCreate('Approve:RoomReservation', 'web')->name;
        $cancelReservation = Permission::findOrCreate('Cancel:RoomReservation', 'web')->name;

        // Escape hatch for a future faculty-affiliated-but-global user
        // (FacultyScope) — exists as data, granted to nobody by default.
        Permission::findOrCreate('ViewAcrossFaculties', 'web');

        $readOnlyPatrimoine = [
            'ViewAny:Building', 'View:Building',
            'ViewAny:Local', 'View:Local',
            'ViewAny:Equipment', 'View:Equipment',
            'ViewAny:PurchaseReference', 'View:PurchaseReference',
            'ViewAny:Assignment', 'View:Assignment',
            'ViewAny:RoomReservation', 'View:RoomReservation',
            'ViewAny:Department', 'View:Department',
            // AcademicTerm is a university-wide referential (like Faculty)
            // — N2 needs to read it to pick a term when filling their
            // department's timetable, but never manages it.
            'ViewAny:AcademicTerm', 'View:AcademicTerm',
        ];

        // N2 administers affectations inside their faculty (matrix:
        // "approve for their faculty: affectations") — create + update
        // (revoke/correct), never delete: history stays A3's call.
        $n2Assignments = ['Create:Assignment', 'Update:Assignment'];

        // N2 administers reservations inside their faculty (matrix:
        // "approve for their faculty: ... reservations") — enters their
        // own timetable directly + approves/rejects ad-hoc requests;
        // never deletes (cancel is the reversible equivalent).
        $n2Reservations = [
            'Create:RoomReservation', 'Update:RoomReservation',
            $manageTimetable, $approveReservation,
        ];

        // N2 manages their own faculty's departments (Phase 5 addendum,
        // 2026-07-06) — the faculty owns the department referential it
        // fills a timetable for. Never deletes: history/reservations may
        // reference it.
        $n2Departments = ['Create:Department', 'Update:Department'];

        // A3 — full CRUD on the inventory referential (matrix: "full CRUD inventory").
        Role::findByName(RoleName::GESTIONNAIRE_PATRIMOINE, 'web')
            ->givePermissionTo([
                ...$fullPatrimoine, $campusMap, $printLabel, $reservationAvailability,
                $manageTimetable, $approveReservation, $cancelReservation,
            ]);

        // N2 — sees their faculty's patrimoine (FacultyScope narrows the queries).
        Role::findByName(RoleName::RESPONSABLE_FACULTE, 'web')
            ->givePermissionTo([
                ...$readOnlyPatrimoine, ...$n2Assignments, ...$n2Reservations, ...$n2Departments,
                $campusMap, $reservationAvailability,
            ]);

        // N3 — university-wide read visibility.
        Role::findByName(RoleName::RECTORAT, 'web')
            ->givePermissionTo([...$readOnlyPatrimoine, $campusMap, $reservationAvailability]);

        // Everyone may look at the campus map — the physical campus is public.
        Role::findByName(RoleName::SERVICE_TECHNIQUE, 'web')->givePermissionTo($campusMap);

        // Enseignant — the only ad-hoc request-initiator role (matrix): no
        // admin-resource browsing (no ViewAny/View:RoomReservation), just
        // the ability to create a `request` row, cancel their own, and see
        // the read-only availability grid before requesting.
        Role::findByName(RoleName::ENSEIGNANT, 'web')->givePermissionTo([
            $campusMap, $reservationAvailability, 'Create:RoomReservation', $cancelReservation,
        ]);

        // Tout utilisateur — read-only timetable/availability per the
        // matrix; booking initiation removed 2026-07-04.
        Role::findByName(RoleName::TOUT_UTILISATEUR, 'web')
            ->givePermissionTo([$campusMap, $reservationAvailability]);
    }
}
