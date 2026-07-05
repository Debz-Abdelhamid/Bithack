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

        foreach (['Building', 'Local', 'Equipment', 'PurchaseReference'] as $model) {
            foreach (self::RESOURCE_METHODS as $method) {
                $fullPatrimoine[] = Permission::findOrCreate("{$method}:{$model}", 'web')->name;
            }
        }

        $campusMap = Permission::findOrCreate('View:CampusMap', 'web')->name;

        // Operational label printing (marks the QR as printed) — its own
        // permission so it stays delegable without write access.
        $printLabel = Permission::findOrCreate('PrintLabel:Equipment', 'web')->name;

        // Escape hatch for a future faculty-affiliated-but-global user
        // (FacultyScope) — exists as data, granted to nobody by default.
        Permission::findOrCreate('ViewAcrossFaculties', 'web');

        $readOnlyPatrimoine = [
            'ViewAny:Building', 'View:Building',
            'ViewAny:Local', 'View:Local',
            'ViewAny:Equipment', 'View:Equipment',
            'ViewAny:PurchaseReference', 'View:PurchaseReference',
        ];

        // A3 — full CRUD on the inventory referential (matrix: "full CRUD inventory").
        Role::findByName(RoleName::GESTIONNAIRE_PATRIMOINE, 'web')
            ->givePermissionTo([...$fullPatrimoine, $campusMap, $printLabel]);

        // N2 — sees their faculty's patrimoine (FacultyScope narrows the queries).
        Role::findByName(RoleName::RESPONSABLE_FACULTE, 'web')
            ->givePermissionTo([...$readOnlyPatrimoine, $campusMap]);

        // N3 — university-wide read visibility.
        Role::findByName(RoleName::RECTORAT, 'web')
            ->givePermissionTo([...$readOnlyPatrimoine, $campusMap]);

        // Everyone may look at the campus map — the physical campus is public.
        Role::findByName(RoleName::SERVICE_TECHNIQUE, 'web')->givePermissionTo($campusMap);
        Role::findByName(RoleName::ENSEIGNANT, 'web')->givePermissionTo($campusMap);
        Role::findByName(RoleName::TOUT_UTILISATEUR, 'web')->givePermissionTo($campusMap);
    }
}
