<?php

namespace Database\Seeders;

use App\Support\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Seed the locked role set (Security.md §3) as data. Business roles start
     * with ZERO permissions — least privilege by default; grants are managed
     * from the Shield UI. `super_admin` needs no permission rows: Shield
     * intercepts the gate (`Gate::before`) for it.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleName::all() as $name) {
            Role::findOrCreate($name, 'web');
        }
    }
}
