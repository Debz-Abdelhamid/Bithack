<?php

namespace Database\Seeders;

use App\Enums\ServiceType;
use App\Models\Faculty;
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
    }
}
