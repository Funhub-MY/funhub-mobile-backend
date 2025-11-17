<?php

namespace App\Console\Commands;

use Exception;
use App\Models\User;
use App\Notifications\AccountUnrestrictedNotification;
use App\Events\OnAccountRestricted;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Models\Audit;
use Illuminate\Support\Facades\DB;

class CheckAccountRestrictions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:check-account-restrictions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and update account restrictions based on restriction end date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('[CheckAccountRestrictions]Checking account restrictions...');
        Log::info('[CheckAccountRestrictions]Checking account restrictions...');

		try {
			// Find admin user to use for audit logs
			$adminUser = User::where('email', 'admin@funhub.my')->first();

			if ($adminUser) {
				// Set admin user as the authenticated user for auditing
				Auth::login($adminUser);
				$this->info("[CheckAccountRestrictions]Using admin user (ID: {$adminUser->id}, Name: {$adminUser->name}) for audit logs");
			} else {
				$this->warn("[CheckAccountRestrictions]Admin user with email admin@funhub.my not found. Trying to find any admin user.");
			}

			// Enable console auditing for this command
			config(['audit.console' => true]);

			// Find users with account_restricted = true and account_restricted_until date in the past
			$users = User::where('account_restricted', true)
				->whereNotNull('account_restricted_until')
				->where('account_restricted_until', '<', now())
				->get();

			$count = $users->count();
			$this->info("[CheckAccountRestrictions]Found {$count} users with expired account restrictions");
			Log::info("[CheckAccountRestrictions]Found {$count} users with expired account restrictions");

			foreach ($users as $user) {
				// Log the user being updated
				$this->info("[CheckAccountRestrictions]Removing account restriction for user: {$user->id} - {$user->name} (restricted until: {$user->account_restricted_until})");

				// Store the old values for manual audit
				$oldValues = [
					'account_restricted' => $user->account_restricted,
					'account_restricted_until' => $user->account_restricted_until
				];

				// Update the user's account_restricted status to false
				$user->account_restricted = false;
				$user->account_restricted_until = null;
				$user->save();

				// Create a manual audit record if needed
				$this->createManualAuditIfNeeded($user, $oldValues, Auth::id());

				// Trigger account restriction change event
				event(new OnAccountRestricted(
					$user,
					true,
					$user->account_restricted_until,
					false,
					null
				));
			}

			$this->info('[CheckAccountRestrictions]Account restrictions check completed successfully');
			Log::info('[CheckAccountRestrictions]Account restrictions check completed successfully');

			// If we logged in an admin user, log them out
			if (Auth::check()) {
				Auth::logout();
			}

			return 0;
		} catch (Exception $e) {
			$this->error("[CheckAccountRestrictions]Error checking account restrictions: {$e->getMessage()}");
			Log::error("[CheckAccountRestrictions]Error in CheckAccountRestrictions command", [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			]);
			return 1;
		}
	}

	/**
	 * Create a manual audit record if the automatic auditing didn't work
	 *
	 * @param User $user The user model that was updated
	 * @param array $oldValues The old values before the update
	 * @param int|null $userId The ID of the user who performed the action
	 * @return void
	 */
	protected function createManualAuditIfNeeded(User $user, array $oldValues, $userId = null)
	{
		// TODO: It does not auto create audit log because audit.php "console=false", if switched to "true" then no longer need this
		$recentAudit = DB::table('audits')
			->where('auditable_type', User::class)
			->where('auditable_id', $user->id)
			->where('created_at', '>=', now()->subSeconds(5))
			->first();

		if (!$recentAudit) {
			// No recent audit found, create one manually
			$this->info("[CheckAccountRestrictions]Creating manual audit for user: {$user->id}");

			$newValues = [
				'account_restricted' => $user->account_restricted,
				'account_restricted_until' => $user->account_restricted_until
			];

			// Create the audit record
			$audit = new Audit();
			$audit->user_id = $userId;
			$audit->user_type = User::class;
			$audit->event = 'updated';
			$audit->auditable_type = User::class;
			$audit->auditable_id = $user->id;
			$audit->old_values = $oldValues;
			$audit->new_values = $newValues;
			$audit->url = 'artisan user:check-account-restrictions';
			$audit->user_agent = 'Console';
			$audit->tags = 'account_restriction';
			$audit->save();

			$this->info("[CheckAccountRestrictions]Manual audit created successfully");
		}
	}
}
