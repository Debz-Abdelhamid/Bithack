<?php

namespace Database\Seeders;

use App\Enums\BuildingStatus;
use App\Enums\EquipmentCondition;
use App\Enums\EquipmentStatus;
use App\Enums\LocalStatus;
use App\Enums\LocalType;
use App\Enums\ServiceType;
use App\Models\Assignment;
use App\Models\Building;
use App\Models\Equipment;
use App\Models\Faculty;
use App\Models\Local;
use App\Models\PurchaseReference;
use App\Models\Service;
use App\Models\User;
use App\Support\RoleName;
use Illuminate\Database\Seeder;

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
}
