<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Approval;
use Illuminate\Support\Facades\Log;
use App\Notifications\NewFunboxRewardApproved;

class ApprovalObserver
{
    /**
     * Handle the Approval "created" event.
     *
     * @param  \App\Models\Approval  $approval
     * @return void
     */
    public function created(Approval $approval)
    {
        //
    }

    /**
     * Handle the Approval "updated" event.
     *
     * @param  \App\Models\Approval  $approval
     * @return void
     */
    public function updated(Approval $approval)
    {
        $approvableType = $approval->approvable_type;
        $approver = User::find($approval->approver_id);
        $status = $approval->approved;

        // Extract the user_id from the decoded data
        $approvalData = json_decode($approval->data, true);
        $user_id = $approvalData['user']['id'];

        // If the approvable type is Reward, approver is admin, and approval status changed to true, then fire NewFunboxRewardApproved Notification        
        if ($approvableType === 'App\Models\Reward' && $approver->hasRole('super_admin') && $status === true) {
            try {
                // Retrieve the user associated with the approval
                $user = User::find($user_id);
                $locale = $user->last_lang ?? config('app.locale');

                // Send the notification
                $user->notify((new NewFunboxRewardApproved($approval, $user))->locale($locale));
            } catch (\Exception $e) {
                Log::error('Error sending notification: ' . $e->getMessage());
            }
        }
    }

    /**
     * Handle the Approval "deleted" event.
     *
     * @param  \App\Models\Approval  $approval
     * @return void
     */
    public function deleted(Approval $approval)
    {
        //
    }

    /**
     * Handle the Approval "restored" event.
     *
     * @param  \App\Models\Approval  $approval
     * @return void
     */
    public function restored(Approval $approval)
    {
        //
    }

    /**
     * Handle the Approval "force deleted" event.
     *
     * @param  \App\Models\Approval  $approval
     * @return void
     */
    public function forceDeleted(Approval $approval)
    {
        //
    }
}
