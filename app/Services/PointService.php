<?php
namespace App\Services;

use App\Models\PointLedger;

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
     * Debit point
     *
     * @param $pointable
     * @param $user
     * @param $amount
     * @param $title
     * @param $remarks
     * @return PointLedger
     */
    public function debit($pointable, $user, $amount, $title, $remarks = null) : PointLedger
    {
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

        return $pointLedger;
    }

    /**
     * Credit point
     *
     * @param $pointable
     * @param $user
     * @param $amount
     * @param $title
     * @param $remarks
     * @return PointLedger
     */
    public function credit($pointable, $user, $amount, $title, $remarks = null) : PointLedger
    {
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

        return $pointLedger;
    }
}
