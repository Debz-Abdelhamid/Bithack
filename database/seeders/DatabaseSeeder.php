<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // Deliberately NOT seeding PermissionSeeder by default (owner
        // decision, 2026-07-08) — every role starts with zero permissions,
        // and the owner assigns them by hand from the Shield UI, logged in
        // as Super Admin. This works because Super Admin is a *gate-based*
        // bypass (config/filament-shield.php: define_via_gate), not a role
        // with permission rows — full access with zero permissions seeded.
        // PermissionSeeder itself still exists and every test still seeds
        // it directly; it's just not part of this default chain anymore.
        if (! app()->environment('production')) {
            $this->call(UserSeeder::class);
        }
    }
}
