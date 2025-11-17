<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use App\Models\User;
use App\Models\MerchantCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class MerchantCategoryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param User $user
     * @return Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_merchant::category');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param MerchantCategory $merchantCategory
     * @return Response|bool
     */
    public function view(User $user, MerchantCategory $merchantCategory): bool
    {
        return $user->can('view_merchant::category');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return Response|bool
     */
    public function create(User $user): bool
    {
        return $user->can('create_merchant::category');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param MerchantCategory $merchantCategory
     * @return Response|bool
     */
    public function update(User $user, MerchantCategory $merchantCategory): bool
    {
        return $user->can('update_merchant::category');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param MerchantCategory $merchantCategory
     * @return Response|bool
     */
    public function delete(User $user, MerchantCategory $merchantCategory): bool
    {
        return $user->can('delete_merchant::category');
    }

    /**
     * Determine whether the user can bulk delete.
     *
     * @param User $user
     * @return Response|bool
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_merchant::category');
    }

    /**
     * Determine whether the user can permanently delete.
     *
     * @param User $user
     * @param MerchantCategory $merchantCategory
     * @return Response|bool
     */
    public function forceDelete(User $user, MerchantCategory $merchantCategory): bool
    {
        return $user->can('force_delete_merchant::category');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     *
     * @param User $user
     * @return Response|bool
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_merchant::category');
    }

    /**
     * Determine whether the user can restore.
     *
     * @param User $user
     * @param MerchantCategory $merchantCategory
     * @return Response|bool
     */
    public function restore(User $user, MerchantCategory $merchantCategory): bool
    {
        return $user->can('restore_merchant::category');
    }

    /**
     * Determine whether the user can bulk restore.
     *
     * @param User $user
     * @return Response|bool
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_merchant::category');
    }

    /**
     * Determine whether the user can replicate.
     *
     * @param User $user
     * @param MerchantCategory $merchantCategory
     * @return Response|bool
     */
    public function replicate(User $user, MerchantCategory $merchantCategory): bool
    {
        return $user->can('replicate_merchant::category');
    }

    /**
     * Determine whether the user can reorder.
     *
     * @param User $user
     * @return Response|bool
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_merchant::category');
    }

}
