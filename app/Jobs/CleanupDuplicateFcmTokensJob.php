<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupDuplicateFcmTokensJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The FCM token to check for duplicates.
     *
     * @var string|null
     */
    protected $fcmToken;

    /**
     * The user ID to exclude from cleanup.
     *
     * @var int|null
     */
    protected $userId;

    /**
     * Create a new job instance.
     *
     * @param string|null $fcmToken
     * @param int|null $userId
     * @return void
     */
    public function __construct(?string $fcmToken = null, ?int $userId = null)
    {
        $this->fcmToken = $fcmToken;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            if ($this->fcmToken && $this->userId) {
                // clean up specific token for a specific user
                $this->cleanupSpecificToken();
            }
        } catch (Exception $e) {
            Log::error('Error cleaning up duplicate FCM tokens: ' . $e->getMessage(), [
                'fcm_token' => $this->fcmToken,
                'user_id' => $this->userId,
                'exception' => $e
            ]);
        }
    }

    /**
     * Clean up a specific FCM token, keeping it only for the specified user.
     *
     * @return void
     */
    private function cleanupSpecificToken()
    {
        if (empty($this->fcmToken)) {
            return;
        }

        // find all users with this token except the current user
        $duplicateUsers = User::where('fcm_token', $this->fcmToken)
            ->where('id', '!=', $this->userId)
            ->get();

        $count = $duplicateUsers->count();
        
        if ($count > 0) {
            // remove the token from all other users
            User::where('fcm_token', $this->fcmToken)
                ->where('id', '!=', $this->userId)
                ->update(['fcm_token' => null]);
            
            Log::info("Removed duplicate FCM token from {$count} users. Token kept for user ID: {$this->userId}");
        }
    }
}
