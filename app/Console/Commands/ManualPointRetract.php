<?php

namespace App\Console\Commands;

use App\Models\Reward;
use App\Models\User;
use App\Services\PointService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ManualPointRetract extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'points:retract {user_ids} {amount}';

    protected $pointService;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function __construct(PointService $pointService)
    {
        parent::__construct();

        $this->pointService = $pointService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $user_ids = $this->argument('user_ids');

        // if user ids are comma separated, explode
        if (strpos($user_ids, ',') !== false) {
            $user_ids = explode(',', $user_ids);
        } else {
            $user_ids = [$user_ids];
        }

        // get reward product
        $reward = Reward::first();

        $amount = $this->argument('amount');
        foreach($user_ids as $user_id) {
            $user = User::find($user_id);
            if (!$user) {
                $this->error('User ' . $user_id . ' not found');
                Log::info('[ManualPointRetract] User ' . $user_id . ' not found');
                continue;
            }
            $balance = $user->point_balance;

            // check if user able to retract the amount based on balance - amount
            if (($balance - $amount) < 0) {
                $this->error('User ' . $user_id . ' unable to retract ' . $amount . ' points. Current balance: ' . $balance);
                Log::info('[ManualPointRetract] User ' . $user_id . ' unable to retract ' . $amount . ' points. Insufficient balance, balance: ' . $balance);
                continue;
            }

            $this->pointService->debit($reward, $user, $amount, 'Manual Point Retract');
            $this->info('Debit ' . $amount . ' Reward(FUNHUB) from user ' . $user_id);
            Log::info('[ManualPointRetract] Debit ' . $amount . ' Reward(FUNHUB) from user ' . $user_id);
        }

        return Command::SUCCESS;
    }
}
