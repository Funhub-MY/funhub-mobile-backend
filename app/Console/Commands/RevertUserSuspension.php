<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class RevertUserSuspension extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:revert-suspension';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks user that has suspended until and revert them if reaches back to Active status';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $users = User::where('suspended_until', '<=', now())->get();
        foreach ($users as $user) {
            $user->suspended_until = null;
            $user->status = User::STATUS_ACTIVE;
            $user->save();
        }
        return Command::SUCCESS;
    }
}
