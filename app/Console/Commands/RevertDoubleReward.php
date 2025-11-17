<?php

namespace App\Console\Commands;

use App\Models\Approval;
use App\Models\Reward;
use App\Services\PointService;
use App\Models\User;
use App\Models\PointLedger;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RevertDoubleReward extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'points:revert-manual-reward {start_date} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $start_date = Carbon::parse($this->argument('start_date'));
        $dryRun = $this->option('dry-run');

        //get all approvals
        $approvals = Approval::where('created_at', '>=', $start_date)
            ->get();

        $reward = Reward::first();

        foreach ($approvals as $approval) {
            // check each approval target user ledger if there's double record
            $data = json_decode($approval->data, true);
            $pointService = new PointService();
            $user = User::find($data['user']['id']);
            $ledgers = PointLedger::where('user_id', $user->id)
                ->orderBy('id', 'asc')
                ->where('created_at', '>=', $start_date)
                ->get();

            // check if same day of approval there's two same credit record side by side
            $lastLedger = null;
            foreach($ledgers as $ledger) {
                if ($ledger->credit) { // only look at credits
                    if ($lastLedger && $lastLedger->credit && $lastLedger->amount == $ledger->amount) {
                        // matched same double record
                        $this->info('Found double record for user ' . $user->id . ' on ' . $ledger->created_at);
                        Log::info('[RevertDoubleReward] Found double record for user ' . $user->id . ' on ' . $ledger->created_at);

                        if (!$dryRun) {
                            $pointService->debit($reward, $user, $ledger->amount, 'Manual Reward Retract');
                        }

                        $this->info('Reverted ' . $ledger->amount . ' points for user ' . $user->id);
                        Log::info('[RevertDoubleReward] Reverted ' . $ledger->amount . ' points for user ' . $user->id);
                    }
                    $lastLedger = $ledger;
                }
            }
        }

        return Command::SUCCESS;
    }
}
