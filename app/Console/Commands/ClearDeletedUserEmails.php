<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ClearDeletedUserEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
	protected $signature = 'users:clear-deleted-emails';

    /**
     * The console command description.
     *
     * @var string
     */
	protected $description = 'Clear email addresses for all deleted user accounts';

    /**
     * Execute the console command.
     *
     * @return int
     */
	public function handle()
	{
		$this->info('Starting to clear email addresses for deleted user accounts...');
		Log::info('Starting to clear email addresses for deleted user accounts...');

		// Find users with account deletions
		$users = User::whereHas('userAccountDeletion')->get();

		$count = 0;

		foreach ($users as $user) {
			$originalEmail = $user->email;

			$user->email = null;
			$user->save();

			$this->line("Cleared email for user ID: {$user->id} ({$originalEmail})");
			Log::info("Cleared email for user ID: {$user->id} ({$originalEmail})");

			$count++;
		}

		$this->info("Completed! Cleared email addresses for {$count} deleted user accounts.");
		Log::info("Completed! Cleared email addresses for {$count} deleted user accounts.");

		return Command::SUCCESS;
	}
}
