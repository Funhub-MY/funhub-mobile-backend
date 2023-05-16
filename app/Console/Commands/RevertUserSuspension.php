<?php

namespace App\Console\Commands;

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
        $users = \App\Models\User::where('suspended_until', '<=', now())->get();
        foreach ($users as $user) {
            $user->suspended_until = null;
            $user->status = \App\Models\User::STATUS_ACTIVE;
            $user->save();
        }
        return Command::SUCCESS;
    }
}
