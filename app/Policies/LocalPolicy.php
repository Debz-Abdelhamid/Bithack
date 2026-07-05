<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Local;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LocalPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Local');
    }

    public function view(AuthUser $authUser, Local $local): bool
    {
        return $authUser->can('View:Local');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Local');
    }

    public function update(AuthUser $authUser, Local $local): bool
    {
        return $authUser->can('Update:Local');
    }

    public function delete(AuthUser $authUser, Local $local): bool
    {
        return $authUser->can('Delete:Local');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Local');
    }

    public function restore(AuthUser $authUser, Local $local): bool
    {
        return $authUser->can('Restore:Local');
    }

    public function forceDelete(AuthUser $authUser, Local $local): bool
    {
        return $authUser->can('ForceDelete:Local');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Local');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Local');
    }

    public function replicate(AuthUser $authUser, Local $local): bool
    {
        return $authUser->can('Replicate:Local');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Local');
    }
}
