<?php

namespace Database\Seeders;

use App\Models\Faculty;
use App\Models\User;
use App\Support\RoleName;
use Illuminate\Database\Seeder;

/**
 * Users-and-roles-only seed, owner-requested (2026-07-08): no buildings,
 * equipment, reservations or tickets — every other entity gets created by
 * hand through the UI while testing each phase. Real institutional domain
 * (matches `patrimo.registration.allowed_domains`'s default), password
 * "password" for every account — a local dev/test credential only.
 *
 * Elevated roles (A3/N2/N3/super admin) deliberately have no
 * `app_authentication_secret` pre-set, so the first login walks through
 * Filament's real MFA enrollment (scan the QR with an authenticator app) —
 * that flow is itself part of what Phase 1 needs testing.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $faculties = [
            'technologie' => Faculty::query()->firstOrCreate(
                ['code' => 'FT'],
                ['name' => 'Faculté de Technologie'],
            ),
            'sciences' => Faculty::query()->firstOrCreate(
                ['code' => 'FS'],
                ['name' => 'Faculté des Sciences'],
            ),
            'medecine' => Faculty::query()->firstOrCreate(
                ['code' => 'FM'],
                ['name' => 'Faculté de Médecine'],
            ),
        ];

        $accounts = [
            ['admin@univ-annaba.dz', 'Super Admin', RoleName::SUPER_ADMIN, null],
            ['a3@univ-annaba.dz', 'Gestionnaire Patrimoine (A3)', RoleName::GESTIONNAIRE_PATRIMOINE, null],
            ['n3@univ-annaba.dz', 'Rectorat (N3)', RoleName::RECTORAT, null],
            ['technique@univ-annaba.dz', 'Service Technique', RoleName::SERVICE_TECHNIQUE, null],
        ];

        foreach ($faculties as $slug => $faculty) {
            $accounts[] = ["n2.{$slug}@univ-annaba.dz", "Responsable Faculté (N2) — {$faculty->name}", RoleName::RESPONSABLE_FACULTE, $faculty->id];
            $accounts[] = ["enseignant.{$slug}@univ-annaba.dz", "Enseignant — {$faculty->name}", RoleName::ENSEIGNANT, $faculty->id];
            $accounts[] = ["utilisateur.{$slug}@univ-annaba.dz", "Tout Utilisateur — {$faculty->name}", RoleName::TOUT_UTILISATEUR, $faculty->id];
        }

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
