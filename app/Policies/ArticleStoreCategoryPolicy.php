<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use App\Models\User;
use App\Models\ArticleStoreCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class ArticleStoreCategoryPolicy
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
        return $user->can('view_any_article::store::category');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param ArticleStoreCategory $articleStoreCategory
     * @return Response|bool
     */
    public function view(User $user, ArticleStoreCategory $articleStoreCategory): bool
    {
        return $user->can('view_article::store::category');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return Response|bool
     */
    public function create(User $user): bool
    {
        return $user->can('create_article::store::category');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param ArticleStoreCategory $articleStoreCategory
     * @return Response|bool
     */
    public function update(User $user, ArticleStoreCategory $articleStoreCategory): bool
    {
        return $user->can('update_article::store::category');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param ArticleStoreCategory $articleStoreCategory
     * @return Response|bool
     */
    public function delete(User $user, ArticleStoreCategory $articleStoreCategory): bool
    {
        return $user->can('delete_article::store::category');
    }

    /**
     * Determine whether the user can bulk delete.
     *
     * @param User $user
     * @return Response|bool
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_article::store::category');
    }

    /**
     * Determine whether the user can permanently delete.
     *
     * @param User $user
     * @param ArticleStoreCategory $articleStoreCategory
     * @return Response|bool
     */
    public function forceDelete(User $user, ArticleStoreCategory $articleStoreCategory): bool
    {
        return $user->can('force_delete_article::store::category');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     *
     * @param User $user
     * @return Response|bool
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_article::store::category');
    }

    /**
     * Determine whether the user can restore.
     *
     * @param User $user
     * @param ArticleStoreCategory $articleStoreCategory
     * @return Response|bool
     */
    public function restore(User $user, ArticleStoreCategory $articleStoreCategory): bool
    {
        return $user->can('restore_article::store::category');
    }

    /**
     * Determine whether the user can bulk restore.
     *
     * @param User $user
     * @return Response|bool
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_article::store::category');
    }

    /**
     * Determine whether the user can replicate.
     *
     * @param User $user
     * @param ArticleStoreCategory $articleStoreCategory
     * @return Response|bool
     */
    public function replicate(User $user, ArticleStoreCategory $articleStoreCategory): bool
    {
        return $user->can('replicate_article::store::category');
    }

    /**
     * Determine whether the user can reorder.
     *
     * @param User $user
     * @return Response|bool
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_article::store::category');
    }

}
