<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AcademicTerm;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AcademicTermPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AcademicTerm');
    }

    public function view(AuthUser $authUser, AcademicTerm $academicTerm): bool
    {
        return $authUser->can('View:AcademicTerm');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AcademicTerm');
    }

    public function update(AuthUser $authUser, AcademicTerm $academicTerm): bool
    {
        return $authUser->can('Update:AcademicTerm');
    }

    public function delete(AuthUser $authUser, AcademicTerm $academicTerm): bool
    {
        return $authUser->can('Delete:AcademicTerm');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AcademicTerm');
    }

    public function restore(AuthUser $authUser, AcademicTerm $academicTerm): bool
    {
        return $authUser->can('Restore:AcademicTerm');
    }

    public function forceDelete(AuthUser $authUser, AcademicTerm $academicTerm): bool
    {
        return $authUser->can('ForceDelete:AcademicTerm');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AcademicTerm');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AcademicTerm');
    }

    public function replicate(AuthUser $authUser, AcademicTerm $academicTerm): bool
    {
        return $authUser->can('Replicate:AcademicTerm');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AcademicTerm');
    }
}
