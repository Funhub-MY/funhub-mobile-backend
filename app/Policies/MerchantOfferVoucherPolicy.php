<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use App\Models\User;
use App\Models\MerchantOfferVoucher;
use Illuminate\Auth\Access\HandlesAuthorization;

class MerchantOfferVoucherPolicy
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
        return $user->can('view_any_merchant::offer::voucher');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param MerchantOfferVoucher $merchantOfferVoucher
     * @return Response|bool
     */
    public function view(User $user, MerchantOfferVoucher $merchantOfferVoucher): bool
    {
        return $user->can('view_merchant::offer::voucher');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return Response|bool
     */
    public function create(User $user): bool
    {
        return $user->can('create_merchant::offer::voucher');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param MerchantOfferVoucher $merchantOfferVoucher
     * @return Response|bool
     */
    public function update(User $user, MerchantOfferVoucher $merchantOfferVoucher): bool
    {
        return $user->can('update_merchant::offer::voucher');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param MerchantOfferVoucher $merchantOfferVoucher
     * @return Response|bool
     */
    public function delete(User $user, MerchantOfferVoucher $merchantOfferVoucher): bool
    {
        return $user->can('delete_merchant::offer::voucher');
    }

    /**
     * Determine whether the user can bulk delete.
     *
     * @param User $user
     * @return Response|bool
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_merchant::offer::voucher');
    }

    /**
     * Determine whether the user can permanently delete.
     *
     * @param User $user
     * @param MerchantOfferVoucher $merchantOfferVoucher
     * @return Response|bool
     */
    public function forceDelete(User $user, MerchantOfferVoucher $merchantOfferVoucher): bool
    {
        return $user->can('force_delete_merchant::offer::voucher');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     *
     * @param User $user
     * @return Response|bool
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_merchant::offer::voucher');
    }

    /**
     * Determine whether the user can restore.
     *
     * @param User $user
     * @param MerchantOfferVoucher $merchantOfferVoucher
     * @return Response|bool
     */
    public function restore(User $user, MerchantOfferVoucher $merchantOfferVoucher): bool
    {
        return $user->can('restore_merchant::offer::voucher');
    }

    /**
     * Determine whether the user can bulk restore.
     *
     * @param User $user
     * @return Response|bool
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_merchant::offer::voucher');
    }

    /**
     * Determine whether the user can replicate.
     *
     * @param User $user
     * @param MerchantOfferVoucher $merchantOfferVoucher
     * @return Response|bool
     */
    public function replicate(User $user, MerchantOfferVoucher $merchantOfferVoucher): bool
    {
        return $user->can('replicate_merchant::offer::voucher');
    }

    /**
     * Determine whether the user can reorder.
     *
     * @param User $user
     * @return Response|bool
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_merchant::offer::voucher');
    }

}
