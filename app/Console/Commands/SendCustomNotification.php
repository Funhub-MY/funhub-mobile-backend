<?php

namespace App\Console\Commands;

use Exception;
use App\Models\User;
use Illuminate\Console\Command;
use App\Models\SystemNotification;
use Illuminate\Support\Facades\Log;
use App\Notifications\CustomNotification;
use Illuminate\Support\Facades\DB;

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
        $currentTime = now();
        $systemNotifications = SystemNotification::whereBetween('scheduled_at', [$currentTime->copy()->subMinutes(5), $currentTime])
            ->whereNull('sent_at')
            ->get();

		if ($systemNotifications->isEmpty()) {
			$this->info('No scheduled notifications found');
			return Command::SUCCESS;
		}

        $this->info('Found ' . $systemNotifications->count() . ' scheduled notification(s)');

        foreach ($systemNotifications as $systemNotification) {
            $this->info('Sending notification ID: ' . $systemNotification->id);
            Log::info('[Custom Notification] Running Notification', [
                'notification' => json_encode($systemNotification),
            ]);

//            $selectedUsers = [];
//
//            if ($systemNotification->all_active_users) {
//                $selectedUsers = User::where('status', User::STATUS_ACTIVE)->get();
//            } else {
//                $selectedUserIds = json_decode($systemNotification->user);
//                $selectedUsers = User::whereIn('id', $selectedUserIds)->get();
//            }

			// Retrieve the users from the pivot table relationship
			$selectedUsers = $systemNotification->all_active_users ? User::where('status', User::STATUS_ACTIVE)->get() : $systemNotification->users;

			// once ready to fire the notification, update the sent_at timestamp
			$systemNotification->update(['sent_at' => now()]); // we dont care whether some users fail to receive we just dont want this to be repeated notificaitons.
			Log::info('[Custom Notification] Notification sent, marked sent at with timestamp', [
				'notification' => $systemNotification->toArray(),
			]);

            foreach ($selectedUsers as $user) {
                $locale = $user->last_lang ?? config('app.locale');

                try {
                    $user->notify((new CustomNotification($systemNotification, $locale)));
					Log::info('Notification sent successfully', [
						'system_notification_id' => $systemNotification->id,
						'user_id' => $user->id,
					]);
				} catch (Exception $e) {
                    Log::error('Error sending custom notification to user: ' . $e->getMessage(), [
                        'system_notification_id' => $systemNotification->id,
                        'user_id' => $user->id,
                    ]);
                }
            }
        }

        return Command::SUCCESS;
    }
}
