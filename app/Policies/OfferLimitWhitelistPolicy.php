<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use App\Models\User;
use App\Models\OfferLimitWhitelist;
use Illuminate\Auth\Access\HandlesAuthorization;

class OfferLimitWhitelistPolicy
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
        return $user->can('view_any_offer::limit::whitelist');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param OfferLimitWhitelist $offerLimitWhitelist
     * @return Response|bool
     */
    public function view(User $user, OfferLimitWhitelist $offerLimitWhitelist): bool
    {
        return $user->can('view_offer::limit::whitelist');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return Response|bool
     */
    public function create(User $user): bool
    {
        return $user->can('create_offer::limit::whitelist');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param OfferLimitWhitelist $offerLimitWhitelist
     * @return Response|bool
     */
    public function update(User $user, OfferLimitWhitelist $offerLimitWhitelist): bool
    {
        return $user->can('update_offer::limit::whitelist');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param OfferLimitWhitelist $offerLimitWhitelist
     * @return Response|bool
     */
    public function delete(User $user, OfferLimitWhitelist $offerLimitWhitelist): bool
    {
        return $user->can('delete_offer::limit::whitelist');
    }

    /**
     * Determine whether the user can bulk delete.
     *
     * @param User $user
     * @return Response|bool
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_offer::limit::whitelist');
    }

    /**
     * Determine whether the user can permanently delete.
     *
     * @param User $user
     * @param OfferLimitWhitelist $offerLimitWhitelist
     * @return Response|bool
     */
    public function forceDelete(User $user, OfferLimitWhitelist $offerLimitWhitelist): bool
    {
        return $user->can('force_delete_offer::limit::whitelist');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     *
     * @param User $user
     * @return Response|bool
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_offer::limit::whitelist');
    }

    /**
     * Determine whether the user can restore.
     *
     * @param User $user
     * @param OfferLimitWhitelist $offerLimitWhitelist
     * @return Response|bool
     */
    public function restore(User $user, OfferLimitWhitelist $offerLimitWhitelist): bool
    {
        return $user->can('restore_offer::limit::whitelist');
    }

    /**
     * Determine whether the user can bulk restore.
     *
     * @param User $user
     * @return Response|bool
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_offer::limit::whitelist');
    }

    /**
     * Determine whether the user can replicate.
     *
     * @param User $user
     * @param OfferLimitWhitelist $offerLimitWhitelist
     * @return Response|bool
     */
    public function replicate(User $user, OfferLimitWhitelist $offerLimitWhitelist): bool
    {
        return $user->can('replicate_offer::limit::whitelist');
    }

    /**
     * Determine whether the user can reorder.
     *
     * @param User $user
     * @return Response|bool
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_offer::limit::whitelist');
    }

}
