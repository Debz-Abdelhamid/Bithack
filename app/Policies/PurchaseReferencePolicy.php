<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseReference;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PurchaseReferencePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PurchaseReference');
    }

    public function view(AuthUser $authUser, PurchaseReference $purchaseReference): bool
    {
        return $authUser->can('View:PurchaseReference');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PurchaseReference');
    }

    public function update(AuthUser $authUser, PurchaseReference $purchaseReference): bool
    {
        return $authUser->can('Update:PurchaseReference');
    }

    public function delete(AuthUser $authUser, PurchaseReference $purchaseReference): bool
    {
        return $authUser->can('Delete:PurchaseReference');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PurchaseReference');
    }

    public function restore(AuthUser $authUser, PurchaseReference $purchaseReference): bool
    {
        return $authUser->can('Restore:PurchaseReference');
    }

    public function forceDelete(AuthUser $authUser, PurchaseReference $purchaseReference): bool
    {
        return $authUser->can('ForceDelete:PurchaseReference');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PurchaseReference');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PurchaseReference');
    }

    public function replicate(AuthUser $authUser, PurchaseReference $purchaseReference): bool
    {
        return $authUser->can('Replicate:PurchaseReference');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PurchaseReference');
    }
}
