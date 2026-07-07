<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Intervention;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class InterventionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Intervention');
    }

    public function view(AuthUser $authUser, Intervention $intervention): bool
    {
        return $authUser->can('View:Intervention');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Intervention');
    }

    public function update(AuthUser $authUser, Intervention $intervention): bool
    {
        return $authUser->can('Update:Intervention');
    }

    public function delete(AuthUser $authUser, Intervention $intervention): bool
    {
        return $authUser->can('Delete:Intervention');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Intervention');
    }

    public function restore(AuthUser $authUser, Intervention $intervention): bool
    {
        return $authUser->can('Restore:Intervention');
    }

    public function forceDelete(AuthUser $authUser, Intervention $intervention): bool
    {
        return $authUser->can('ForceDelete:Intervention');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Intervention');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Intervention');
    }

    public function replicate(AuthUser $authUser, Intervention $intervention): bool
    {
        return $authUser->can('Replicate:Intervention');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Intervention');
    }

    /**
     * A Service technique member logs their own report/cost/completion on
     * an intervention they're assigned to — narrower than full `update`
     * (which stays A3-only), compares the technician id rather than the
     * role name (Claude.md §4: policies check permissions/data, never
     * `hasRole()` string comparisons).
     */
    public function logWork(AuthUser $authUser, Intervention $intervention): bool
    {
        return $authUser->can('LogWork:Intervention') && $intervention->technician_id === $authUser->getAuthIdentifier();
    }
}
