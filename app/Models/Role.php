<?php

namespace App\Models;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Security.md §8 — role lifecycle (create/rename/delete) is audit-logged;
 * permission/role attach-detach is covered by the spatie events listeners in
 * AppServiceProvider. Registered in config/permission.php (models.role).
 */
class Role extends SpatieRole
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('rbac')
            ->logOnly(['name', 'guard_name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
