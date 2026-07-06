<?php

namespace Database\Seeders;

use App\Enums\BuildingStatus;
use App\Enums\EquipmentCondition;
use App\Enums\EquipmentStatus;
use App\Enums\LocalStatus;
use App\Enums\LocalType;
use App\Enums\ReservationLevel;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\ServiceType;
use App\Models\AcademicTerm;
use App\Models\Assignment;
use App\Models\Building;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\Faculty;
use App\Models\Local;
use App\Models\PurchaseReference;
use App\Models\RoomReservation;
use App\Models\Service;
use App\Models\User;
use App\Support\RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Obviously-fake demo data (Claude.md §6 — no real university/personal data).
 * One account per role, all with the local password "password".
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $technology = Faculty::query()->firstOrCreate(
            ['code' => 'FT'],
            ['name' => 'Faculty of Technology (demo)'],
        );

        $sciences = Faculty::query()->firstOrCreate(
            ['code' => 'FS'],
            ['name' => 'Faculty of Sciences (demo)'],
        );

        Service::query()->firstOrCreate(
            ['faculty_id' => null, 'name' => 'Central Technical Service (demo)'],
            ['type' => ServiceType::Service],
        );

        Service::query()->firstOrCreate(
            ['faculty_id' => $technology->id, 'name' => 'Computer Science Laboratory (demo)'],
            ['type' => ServiceType::Labo],
        );

        $accounts = [
            ['admin@demo.ubma.dz', 'Demo Admin', RoleName::SUPER_ADMIN, null],
            ['a3@demo.ubma.dz', 'Demo Asset Manager', RoleName::GESTIONNAIRE_PATRIMOINE, null],
            ['n2@demo.ubma.dz', 'Demo Faculty Head', RoleName::RESPONSABLE_FACULTE, $technology->id],
            ['n3@demo.ubma.dz', 'Demo Rectorate', RoleName::RECTORAT, null],
            ['technique@demo.ubma.dz', 'Demo Technician', RoleName::SERVICE_TECHNIQUE, null],
            ['enseignant@demo.ubma.dz', 'Demo Teacher', RoleName::ENSEIGNANT, $sciences->id],
            ['utilisateur@demo.ubma.dz', 'Demo User', RoleName::TOUT_UTILISATEUR, $sciences->id],
        ];

        foreach ($accounts as [$email, $name, $role, $facultyId]) {
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => 'password',
                    'faculty_id' => $facultyId,
                    // Provisioned accounts are pre-verified (Security.md §2).
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$role]);
        }

        $this->seedCampus($technology, $sciences);
        $this->seedEquipments();
        $this->seedAssignments();
        $this->seedDepartmentsAndTerms($technology, $sciences);
        $this->seedReservations();
    }

    /**
     * Phase 5 addendum (2026-07-06): 2 departments per faculty + 2
     * academic terms — a "current" one (covers today) and a "next" one,
     * illustrating "the faculty fills each department's timetable one
     * semester at a time".
     */
    private function seedDepartmentsAndTerms(Faculty $technology, Faculty $sciences): void
    {
        $departments = [
            ['Computer Science (demo)', 'CS', $technology->id],
            ['Electronics (demo)', 'ELEC', $technology->id],
            ['Mathematics (demo)', 'MATH', $sciences->id],
            ['Physics (demo)', 'PHYS', $sciences->id],
        ];

        foreach ($departments as [$name, $code, $facultyId]) {
            Department::query()->firstOrCreate(
                ['faculty_id' => $facultyId, 'name' => $name],
                ['code' => $code],
            );
        }

        AcademicTerm::query()->firstOrCreate(
            ['academic_year' => '2025-2026', 'semester' => 2],
            ['start_date' => '2026-02-01', 'end_date' => '2026-07-15'],
        );

        AcademicTerm::query()->firstOrCreate(
            ['academic_year' => '2026-2027', 'semester' => 1],
            ['start_date' => '2026-09-15', 'end_date' => '2027-01-25'],
        );
    }

    /**
     * Demo buildings clustered around the real UBMA campus center used by the
     * map (36.8133517, 7.7198301) — names/coords are obviously fake.
     */
    private function seedCampus(Faculty $technology, Faculty $sciences): void
    {
        $buildings = [
            ['BAT-A', 'Building A (demo)', $technology->id, 36.8140, 7.7205, [
                ['A-101', 'Amphi A1', LocalType::Amphi, 0, 250],
                ['A-102', 'Classroom A2', LocalType::SalleCours, 1, 60],
                ['A-LAB1', 'Networks Lab', LocalType::Labo, 2, 30],
            ]],
            ['BAT-B', 'Building B (demo)', $sciences->id, 36.8128, 7.7188, [
                ['B-201', 'Amphi B1', LocalType::Amphi, 0, 180],
                ['B-202', 'Chemistry Lab', LocalType::Labo, 1, 24],
            ]],
            ['BIB-C', 'Central Library (demo)', null, 36.8134, 7.7215, [
                ['C-301', 'Reading Room', LocalType::SalleCours, 0, 120],
                ['C-302', 'Meeting Room C1', LocalType::SalleReunion, 1, 16],
            ]],
        ];

        foreach ($buildings as [$code, $name, $facultyId, $lat, $lng, $locals]) {
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

            foreach ($locals as [$localCode, $localName, $type, $floor, $capacity]) {
                Local::query()->firstOrCreate(
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
    }

    /**
     * Demo assets — inventory codes are fixed (not auto-generated) so the
     * seeder stays idempotent across re-runs. Each creation still goes
     * through the observer, so every equipment gets its QR token.
     */
    private function seedEquipments(): void
    {
        $order = PurchaseReference::query()->firstOrCreate(
            ['external_purchase_id' => 'R7-2025-0042 (demo)'],
            ['supplier' => 'Demo IT Supplier Ltd.', 'order_date' => '2025-09-15'],
        );

        $networksLab = Local::query()->where('code', 'A-LAB1')->first();
        $amphiA = Local::query()->where('code', 'A-101')->first();
        $readingRoom = Local::query()->where('code', 'C-301')->first();

        $equipments = [
            ['UBMA-2025-00001', 'Desktop computer (demo)', 'informatique', $networksLab?->id, $order->id, EquipmentStatus::InService, EquipmentCondition::Good],
            ['UBMA-2025-00002', 'Video projector (demo)', 'informatique', $amphiA?->id, $order->id, EquipmentStatus::InService, EquipmentCondition::New],
            ['UBMA-2025-00003', 'Air conditioner (demo)', 'electrique', $readingRoom?->id, null, EquipmentStatus::UnderRepair, EquipmentCondition::Worn],
            ['UBMA-2025-00004', 'Office desk (demo)', 'mobilier', null, null, EquipmentStatus::InService, EquipmentCondition::Good],
        ];

        foreach ($equipments as [$code, $designation, $category, $localId, $orderId, $status, $condition]) {
            Equipment::query()->firstOrCreate(
                ['inventory_code' => $code],
                [
                    'designation' => $designation,
                    'category' => $category,
                    'local_id' => $localId,
                    'purchase_reference_id' => $orderId,
                    'status' => $status,
                    'condition' => $condition,
                ],
            );
        }
    }

    private function seedAssignments(): void
    {
        $desktop = Equipment::query()->where('inventory_code', 'UBMA-2025-00001')->first();
        $lab = Service::query()->where('name', 'Computer Science Laboratory (demo)')->first();
        $a3 = User::query()->where('email', 'a3@demo.ubma.dz')->first();

        if ($desktop === null || $lab === null || $a3 === null) {
            return;
        }

        Assignment::query()->firstOrCreate(
            [
                'equipment_id' => $desktop->id,
                'service_id' => $lab->id,
                'start_date' => '2025-10-01',
            ],
            [
                'assigned_by_user_id' => $a3->id,
                'notes' => 'Demo assignment.',
            ],
        );
    }

    /**
     * One `timetable` slot (N2-authored, confirmed, department + term
     * attached) + one `request` (Enseignant ad-hoc, pending) — illustrates
     * the 2026-07-06 source split. The timetable slot's time aligns with
     * TimetableBuilder::TIME_SLOTS[0] (08:00–09:30) so it renders in the
     * grid; dates are relative so re-seeding never lands in the past.
     */
    private function seedReservations(): void
    {
        $amphiA = Local::query()->where('code', 'A-101')->first();
        $classroomA = Local::query()->where('code', 'A-102')->first();
        $n2 = User::query()->where('email', 'n2@demo.ubma.dz')->first();
        $teacher = User::query()->where('email', 'enseignant@demo.ubma.dz')->first();
        $csDepartment = Department::query()->where('name', 'Computer Science (demo)')->first();
        $currentTerm = AcademicTerm::current()->first();

        if ($amphiA === null || $classroomA === null || $n2 === null || $teacher === null) {
            return;
        }

        $nextMonday = Carbon::parse('next monday')->setTime(8, 0);

        RoomReservation::query()->firstOrCreate(
            ['local_id' => $amphiA->id, 'start_at' => $nextMonday],
            [
                'source' => ReservationSource::Timetable,
                'requested_by_user_id' => $n2->id,
                'teacher_user_id' => $teacher->id,
                'department_id' => $csDepartment?->id,
                'academic_term_id' => $currentTerm?->id,
                'module_name' => 'Algorithms 101 (demo)',
                'level' => ReservationLevel::L1,
                'end_at' => $nextMonday->copy()->addMinutes(90),
                'recurring_rule' => $currentTerm !== null ? 'WEEKLY;UNTIL='.$currentTerm->end_date->toDateString() : null,
                'status' => ReservationStatus::Confirmed,
            ],
        );

        $nextTuesday = Carbon::parse('next tuesday')->setTime(14, 0);

        RoomReservation::query()->firstOrCreate(
            ['local_id' => $classroomA->id, 'start_at' => $nextTuesday],
            [
                'source' => ReservationSource::Request,
                'requested_by_user_id' => $teacher->id,
                'teacher_user_id' => $teacher->id,
                'purpose' => 'Makeup class (demo)',
                'end_at' => $nextTuesday->copy()->addMinutes(90),
                'status' => ReservationStatus::Pending,
            ],
        );
    }
}
