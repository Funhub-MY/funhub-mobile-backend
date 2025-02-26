<?php
namespace App\Services;

use App\Models\PointLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PointService
{
    public function getPointLedger($user)
    {
        return PointLedger::where('user_id', $user->id)->get();
    }

    public function getBalanceOfUser($user)
    {
        $latestLedger = $user->pointLedgers()->orderBy('id', 'desc')->first();
        return $latestLedger ? $latestLedger->balance : 0;
    }

    /**
     * Debit point with database locking to prevent race conditions
     *
     * @param $pointable
     * @param $user
     * @param $amount
     * @param $title
     * @param $remarks
     * @return PointLedger
     * @throws \Exception
     */
    public function debit($pointable, $user, $amount, $title, $remarks = null) : PointLedger
    {
        return DB::transaction(function () use ($pointable, $user, $amount, $title, $remarks) {
            // lock only this user's point ledgers to prevent concurrent modifications
            PointLedger::where('user_id', $user->id)
                ->orderBy('id', 'desc')
                ->limit(1)
                ->lockForUpdate()
                ->first();
            
            // get the latest balance with the lock in place
            $userBalance = $this->getBalanceOfUser($user);

            if ($userBalance < $amount) {
                throw new \Exception('Insufficient balance');
            }

            $pointLedger = new PointLedger;
            $pointLedger->user_id = $user->id;
            $pointLedger->pointable_id = $pointable->id;
            $pointLedger->pointable_type = get_class($pointable);
            $pointLedger->title = $title;
            $pointLedger->amount = $amount;
            $pointLedger->debit = true;
            $pointLedger->balance = $userBalance - $amount;
            $pointLedger->remarks = $remarks;
            $pointLedger->save();

            $this->updatePointBalanceOfUser($user, $pointLedger->balance);

            return $pointLedger;
        }, 5); // 5 retries in case of deadlock
    }

    /**
     * Credit point with database locking to prevent race conditions
     *
     * @param $pointable
     * @param $user
     * @param $amount
     * @param $title
     * @param $remarks
     * @return PointLedger
     * @throws \Exception
     */
    public function credit($pointable, $user, $amount, $title, $remarks = null) : PointLedger
    {
        return DB::transaction(function () use ($pointable, $user, $amount, $title, $remarks) {
            // lock only this user's point ledgers to prevent concurrent modifications
            PointLedger::where('user_id', $user->id)
                ->orderBy('id', 'desc')
                ->limit(1)
                ->lockForUpdate()
                ->first();
            
            // get the latest balance with the lock in place
            $userBalance = $this->getBalanceOfUser($user);

            $pointLedger = new PointLedger;
            $pointLedger->user_id = $user->id;
            $pointLedger->pointable_id = $pointable->id;
            $pointLedger->pointable_type = get_class($pointable);
            $pointLedger->title = $title;
            $pointLedger->amount = $amount;
            $pointLedger->credit = true;
            $pointLedger->balance = $userBalance + $amount;
            $pointLedger->remarks = $remarks;
            $pointLedger->save();

            $this->updatePointBalanceOfUser($user, $pointLedger->balance);

            return $pointLedger;
        }, 5); // 5 retries in case of deadlock
    }

    /**
     * Update the point balance of a user with the provided balance
     *
     * @param User $user
     * @param int|null $balance If null, will recalculate from the latest ledger
     * @return void
     */
    public function updatePointBalanceOfUser($user, $balance = null)
    {
        if ($balance === null) {
            $balance = $this->getBalanceOfUser($user);
        }
        
        $user->point_balance = $balance;
        $user->save();
    }
}
