<?php

namespace Database\Seeders;

use App\Enums\BuildingStatus;
use App\Enums\EquipmentCondition;
use App\Enums\EquipmentStatus;
use App\Enums\InterventionStatus;
use App\Enums\LocalStatus;
use App\Enums\LocalType;
use App\Enums\ReservationLevel;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\ServiceType;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\AcademicTerm;
use App\Models\Assignment;
use App\Models\Building;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\Faculty;
use App\Models\Intervention;
use App\Models\Local;
use App\Models\MaintenanceTicket;
use App\Models\RoomReservation;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reference/inventory data on top of UserSeeder's accounts (owner request,
 * 2026-07-08) — buildings/rooms/equipment/services/assignments/departments/
 * terms/reservations/tickets, obviously-fake (Claude.md §6), idempotent
 * (firstOrCreate throughout, safe to re-run). Deliberately leaves a few
 * things for manual testing: no ad-hoc request left pending (owner
 * confirms/rejects it live), no ticket created via the actual QR-scan flow
 * (owner exercises that flow itself, Phase 6).
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $faculties = Faculty::query()->get()->keyBy('code');

        $services = $this->seedServices($faculties);
        $buildings = $this->seedCampus($faculties);
        $equipments = $this->seedEquipments($buildings);
        $this->seedAssignments($equipments, $services);
        $departments = $this->seedDepartments($faculties);
        $term = $this->seedAcademicTerm();
        $this->seedReservations($buildings, $departments, $term);
        $this->seedMaintenanceTickets($equipments);
    }

    /**
     * @param  Collection<string, Faculty>  $faculties
     * @return Collection<string, Service>
     */
    private function seedServices(Collection $faculties): Collection
    {
        $rows = [
            ['Service Technique Central (demo)', null, ServiceType::Service],
            ['Laboratoire Informatique (demo)', $faculties['FT']->id, ServiceType::Labo],
            ['Laboratoire de Physique (demo)', $faculties['FS']->id, ServiceType::Labo],
            ['Laboratoire de Biologie (demo)', $faculties['FM']->id, ServiceType::Labo],
        ];

        return collect($rows)->mapWithKeys(function (array $row): array {
            [$name, $facultyId, $type] = $row;

            $service = Service::query()->firstOrCreate(
                ['name' => $name],
                ['faculty_id' => $facultyId, 'type' => $type],
            );

            return [$name => $service];
        });
    }

    /**
     * Technologie already has a manually-created building from the owner's
     * own Phase 2 testing (BAT-INF) — left untouched, only Sciences/
     * Médecine/shared are seeded here.
     *
     * @param  Collection<string, Faculty>  $faculties
     * @return Collection<string, Local>
     */
    private function seedCampus(Collection $faculties): Collection
    {
        $rows = [
            ['BAT-SCI', 'Bâtiment Sciences (demo)', $faculties['FS']->id, 36.8129, 7.7190, [
                ['SCI-101', 'Amphi Sciences 1', LocalType::Amphi, 0, 220],
                ['SCI-LAB1', 'Laboratoire de Chimie', LocalType::Labo, 1, 28],
                ['SCI-102', 'Salle de cours Sciences 2', LocalType::SalleCours, 1, 45],
            ]],
            ['BAT-MED', 'Bâtiment Médecine (demo)', $faculties['FM']->id, 36.8140, 7.7212, [
                ['MED-101', 'Amphi Médecine 1', LocalType::Amphi, 0, 180],
                ['MED-LAB1', 'Laboratoire de Biologie', LocalType::Labo, 1, 20],
            ]],
            ['BIB-C', 'Bibliothèque Centrale (demo)', null, 36.8135, 7.7202, [
                ['BIB-301', 'Salle de lecture', LocalType::SalleCours, 0, 100],
                ['BIB-302', 'Salle de réunion', LocalType::SalleReunion, 1, 12],
            ]],
        ];

        $locals = collect();

        foreach ($rows as [$code, $name, $facultyId, $lat, $lng, $rooms]) {
            $building = Building::query()->firstOrCreate(
                ['code' => $code],
                [
                    'faculty_id' => $facultyId,
                    'name' => $name,
                    'campus' => 'Main Campus (demo)',
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'status' => BuildingStatus::Active,
                ],
            );

            foreach ($rooms as [$localCode, $localName, $type, $floor, $capacity]) {
                $locals[$localCode] = Local::query()->firstOrCreate(
                    ['code' => $localCode],
                    [
                        'building_id' => $building->id,
                        'name' => $localName,
                        'type' => $type,
                        'floor' => $floor,
                        'capacity' => $capacity,
                        'status' => LocalStatus::Available,
                    ],
                );
            }
        }

        // Fold in whatever rooms already exist under the owner's own
        // Technologie building, keyed by code, so later steps (equipment,
        // reservations) can place things there too.
        foreach (Local::query()->whereHas('building', fn ($q) => $q->where('code', 'BAT-INF'))->get() as $local) {
            $locals[$local->code] = $local;
        }

        return $locals;
    }

    /**
     * @param  Collection<string, Local>  $locals
     * @return Collection<int, Equipment>
     */
    private function seedEquipments(Collection $locals): Collection
    {
        // Fixed inventory codes (not auto-generated) so re-running this
        // seeder is idempotent — two physically distinct desks in the same
        // room otherwise have identical (designation, local, condition)
        // and firstOrCreate would collapse them into one row.
        $rows = [
            ['DEMO-0001', 'Vidéoprojecteur', 'informatique', 'Amphi-22', EquipmentStatus::InService, EquipmentCondition::New],
            ['DEMO-0002', 'Ordinateur de bureau', 'informatique', 'LAB-1', EquipmentStatus::InService, EquipmentCondition::Good],
            ['DEMO-0003', 'Ordinateur de bureau', 'informatique', 'LAB-1', EquipmentStatus::InService, EquipmentCondition::Good],
            ['DEMO-0004', 'Ordinateur de bureau', 'informatique', 'LAB-1', EquipmentStatus::InService, EquipmentCondition::Worn],
            ['DEMO-0005', 'Climatiseur', 'electrique', 'LAB-1', EquipmentStatus::InService, EquipmentCondition::Good],
            ['DEMO-0006', 'Vidéoprojecteur', 'informatique', 'SCI-101', EquipmentStatus::InService, EquipmentCondition::New],
            ['DEMO-0007', 'Microscope', 'informatique', 'SCI-LAB1', EquipmentStatus::InService, EquipmentCondition::Good],
            ['DEMO-0008', 'Microscope', 'informatique', 'SCI-LAB1', EquipmentStatus::UnderRepair, EquipmentCondition::Worn],
            ['DEMO-0009', 'Bureau', 'mobilier', 'SCI-102', EquipmentStatus::InService, EquipmentCondition::Good],
            ['DEMO-0010', 'Vidéoprojecteur', 'informatique', 'MED-101', EquipmentStatus::InService, EquipmentCondition::New],
            ['DEMO-0011', 'Réfrigérateur de laboratoire', 'electrique', 'MED-LAB1', EquipmentStatus::InService, EquipmentCondition::Good],
            ['DEMO-0012', 'Chaise', 'mobilier', 'BIB-301', EquipmentStatus::InService, EquipmentCondition::Good],
        ];

        return collect($rows)->map(function (array $row) use ($locals): ?Equipment {
            [$inventoryCode, $designation, $category, $localCode, $status, $condition] = $row;

            $local = $locals[$localCode] ?? null;

            if ($local === null) {
                return null;
            }

            return Equipment::query()->firstOrCreate(
                ['inventory_code' => $inventoryCode],
                ['designation' => $designation, 'local_id' => $local->id, 'category' => $category, 'status' => $status, 'condition' => $condition],
            );
        })->filter();
    }

    /**
     * @param  Collection<int, Equipment>  $equipments
     * @param  Collection<string, Service>  $services
     */
    private function seedAssignments(Collection $equipments, Collection $services): void
    {
        $a3 = User::query()->where('email', 'a3@univ-annaba.dz')->first();
        $lab = $services['Laboratoire Informatique (demo)'] ?? null;
        $desktop = $equipments->firstWhere('designation', 'Ordinateur de bureau');

        if ($a3 === null || $lab === null || $desktop === null) {
            return;
        }

        Assignment::query()->firstOrCreate(
            ['equipment_id' => $desktop->id, 'service_id' => $lab->id, 'start_date' => '2026-01-15'],
            ['assigned_by_user_id' => $a3->id, 'notes' => 'Demo assignment.'],
        );
    }

    /**
     * @param  Collection<string, Faculty>  $faculties
     * @return Collection<string, Department>
     */
    private function seedDepartments(Collection $faculties): Collection
    {
        $rows = [
            ['Informatique (demo)', 'INFO', $faculties['FT']->id],
            ['Physique (demo)', 'PHYS', $faculties['FS']->id],
            ['Médecine Générale (demo)', 'MEDG', $faculties['FM']->id],
        ];

        return collect($rows)->mapWithKeys(function (array $row): array {
            [$name, $code, $facultyId] = $row;

            $department = Department::query()->firstOrCreate(
                ['faculty_id' => $facultyId, 'name' => $name],
                ['code' => $code],
            );

            return [$code => $department];
        });
    }

    /**
     * A fixed semester 2 term spanning "now" (2026-07-08) — simple and
     * obviously correct beats dynamically computing an academic calendar
     * for a one-off reference-data seed; if this is still running past
     * mid-2027 the owner is re-seeding a stale environment regardless.
     */
    private function seedAcademicTerm(): AcademicTerm
    {
        return AcademicTerm::query()->firstOrCreate(
            ['academic_year' => '2025-2026', 'semester' => 2],
            ['start_date' => '2026-02-01', 'end_date' => '2026-07-15'],
        );
    }

    /**
     * Two PARALLEL classes in the very same Monday 08:00 slot (owner-
     * clarified 2026-07-09) — different rooms AND different student groups,
     * so both are legal and both must show stacked in that grid cell. This
     * is the seeded proof that the "second class overwrites the first" bug
     * is gone; it also gives the group filter something to narrow.
     *
     * @param  Collection<string, Local>  $locals
     * @param  Collection<string, Department>  $departments
     */
    private function seedReservations(Collection $locals, Collection $departments, AcademicTerm $term): void
    {
        $amphi = $locals['SCI-101'] ?? null;
        $room = $locals['SCI-102'] ?? null;
        $n2 = User::query()->where('email', 'n2.sciences@univ-annaba.dz')->first();
        $teacher = User::query()->where('email', 'enseignant.sciences@univ-annaba.dz')->first();
        $department = $departments['PHYS'] ?? null;

        if ($amphi === null || $n2 === null || $teacher === null || $department === null) {
            return;
        }

        $nextMonday = Carbon::parse('next monday')->setTime(8, 0);

        $parallelClasses = [
            [$amphi, 'Physique Générale (demo)', 'Groupe A'],
            [$room, 'Mécanique du Point (demo)', 'Groupe B'],
        ];

        foreach ($parallelClasses as [$local, $module, $group]) {
            RoomReservation::query()->firstOrCreate(
                ['local_id' => $local->id, 'start_at' => $nextMonday],
                [
                    'source' => ReservationSource::Timetable,
                    'requested_by_user_id' => $n2->id,
                    'teacher_user_id' => $teacher->id,
                    'department_id' => $department->id,
                    'academic_term_id' => $term->id,
                    'module_name' => $module,
                    'level' => ReservationLevel::L1,
                    'student_group' => $group,
                    'end_at' => $nextMonday->copy()->addMinutes(90),
                    'recurring_rule' => 'WEEKLY;UNTIL='.$term->end_date->toDateString(),
                    'status' => ReservationStatus::Confirmed,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, Equipment>  $equipments
     */
    private function seedMaintenanceTickets(Collection $equipments): void
    {
        $reporter = User::query()->where('email', 'utilisateur.technologie@univ-annaba.dz')->first();
        $technician = User::query()->where('email', 'technique@univ-annaba.dz')->first();

        if ($reporter === null) {
            return;
        }

        $rows = [
            // [designation to find, description, priority, status, has intervention, intervention status]
            ['Climatiseur', 'Le climatiseur fait un bruit fort et ne refroidit plus (demo).', TicketPriority::Urgent, TicketStatus::New, false, null],
            ['Microscope', 'Objectif rayé, image floue (demo).', TicketPriority::Standard, TicketStatus::Assigned, true, InterventionStatus::Planned],
            ['Réfrigérateur de laboratoire', 'Porte ne ferme plus correctement (demo).', TicketPriority::Standard, TicketStatus::InProgress, true, InterventionStatus::InProgress],
            ['Chaise', 'Pied cassé (demo).', TicketPriority::Standard, TicketStatus::Resolved, true, InterventionStatus::Done],
        ];

        foreach ($rows as [$designation, $description, $priority, $status, $hasIntervention, $interventionStatus]) {
            $equipment = $equipments->firstWhere('designation', $designation);

            if ($equipment === null) {
                continue;
            }

            $ticket = MaintenanceTicket::query()->firstOrCreate(
                ['equipment_id' => $equipment->id, 'description' => $description],
                [
                    'reported_by_user_id' => $reporter->id,
                    'source' => TicketSource::Manual,
                    'priority' => $priority,
                    'status' => $status,
                ],
            );

            if ($hasIntervention && $technician !== null) {
                Intervention::query()->firstOrCreate(
                    ['maintenance_ticket_id' => $ticket->id],
                    ['technician_id' => $technician->id, 'status' => $interventionStatus],
                );
            }
        }
    }
}
