<?php

namespace App\Console\Commands;

use App\Models\MerchantOffer;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReleasePendingMerchantOffer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:release-merchant-offer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release pending merchant offer to user wallet';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // get all pending transactions
        $transactions = \App\Models\Transaction::where('status', 'pending')->get();
        foreach ($transactions as $transaction) {
            $minutesToRelease = config('app.release_offer_stock_after_min');
            // check if transaction is expired
            if (Carbon::parse($transaction->created_at)->addMinutes($minutesToRelease)->isPast()) {
                // release stock
                Log::info('[AutoRelease] Transaction past '. $minutesToRelease . ', marked as failed, ID:' . $transaction->id);
                $transaction->status = Transaction::STATUS_FAILED;
                $transaction->save();

                if ($transaction->transactional_type == MerchantOffer::class) {
                    // failed
                    $merchantOffer = MerchantOffer::where('id', $transaction->transactionable_id)->first();
                    $merchantOffer->claims()->updateExistingPivot($transaction->user_id, [
                        'status' => \App\Models\MerchantOffer::CLAIM_FAILED
                    ]);

                    // add claims where user_id quantity back to merchantOffer quantity
                    $claim = MerchantOffer::where('id', $transaction->transactionable_id)->claims()
                        ->wherePivot('user_id', $transaction->user_id)
                        ->first();
                    if ($claim) {
                        $merchantOffer->quantity = $merchantOffer->quantity + $claim->pivot->quantity;
                        $merchantOffer->save();
                    }
                }
            }
        }
        return Command::SUCCESS;
    }
}
