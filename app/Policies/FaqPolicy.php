<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use App\Models\User;
use App\Models\Faq;
use Illuminate\Auth\Access\HandlesAuthorization;

class FaqPolicy
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
        return $user->can('view_any_faq');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param Faq $faq
     * @return Response|bool
     */
    public function view(User $user, Faq $faq): bool
    {
        return $user->can('view_faq');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return Response|bool
     */
    public function create(User $user): bool
    {
        return $user->can('create_faq');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param Faq $faq
     * @return Response|bool
     */
    public function update(User $user, Faq $faq): bool
    {
        return $user->can('update_faq');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param Faq $faq
     * @return Response|bool
     */
    public function delete(User $user, Faq $faq): bool
    {
        return $user->can('delete_faq');
    }

    /**
     * Determine whether the user can bulk delete.
     *
     * @param User $user
     * @return Response|bool
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_faq');
    }

    /**
     * Determine whether the user can permanently delete.
     *
     * @param User $user
     * @param Faq $faq
     * @return Response|bool
     */
    public function forceDelete(User $user, Faq $faq): bool
    {
        return $user->can('force_delete_faq');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     *
     * @param User $user
     * @return Response|bool
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_faq');
    }

    /**
     * Determine whether the user can restore.
     *
     * @param User $user
     * @param Faq $faq
     * @return Response|bool
     */
    public function restore(User $user, Faq $faq): bool
    {
        return $user->can('restore_faq');
    }

    /**
     * Determine whether the user can bulk restore.
     *
     * @param User $user
     * @return Response|bool
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_faq');
    }

    /**
     * Determine whether the user can replicate.
     *
     * @param User $user
     * @param Faq $faq
     * @return Response|bool
     */
    public function replicate(User $user, Faq $faq): bool
    {
        return $user->can('replicate_faq');
    }

    /**
     * Determine whether the user can reorder.
     *
     * @param User $user
     * @return Response|bool
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_faq');
    }

}
