<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use App\Models\SystemNotification;
use Illuminate\Support\Facades\Log;
use App\Notifications\CustomNotification;

class SendCustomNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send-custom-notification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a custom notification to selected users at a scheduled time';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $currentTime = now();

            $systemNotifications = SystemNotification::whereBetween('scheduled_at', [$currentTime->copy()->subMinutes(10), $currentTime->copy()->addMinutes(10)])
                ->whereNull('sent_at')
                ->get();

            if ($systemNotifications->count() == 0) {
                // no scheduled notification
                $this->info('No scheduled notification found');

                return Command::SUCCESS;
            } else {
                $this->info('Found ' . $systemNotifications->count() . ' scheduled notification(s)');

                foreach ($systemNotifications as $systemNotification) {
                    $this->info('Sending notification ID: ' . $systemNotification->id);
                    Log::info('[Custom Notification] Running Notification', [
                        'notification' => json_encode($systemNotification),
                    ]);

                    // Get the selected user Ids
                    if ($systemNotification->all_active_users) {
                        $selectedUserIds = User::where('status', 1)->pluck('id')->toArray();
                    } else {
                        $selectedUserIds = json_decode($systemNotification->user);
                    }

                    foreach ($selectedUserIds as $userId) {
                        $user = User::where('id', $userId)->first();
                        $user->notify(new CustomNotification($systemNotification));
                    }

                    Log::info('[Custom Notification] Scheduled notification has been sent to selected users', [
                        'user_ids' => $selectedUserIds,
                    ]);

                    // After sending notification, add timestamp to sent_at column in table
                    $systemNotification->update(['sent_at' => now()]);
                }
                return Command::SUCCESS;
            }
        } catch (\Exception $e) {
            Log::error('Error sending custom notification to selected users: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
