<?php
namespace App\Services;

use App\Models\PointLedger;

class PointService
{
    public function getPointLedger($user)
    {
        return PointLedger::where('user_id', $user->id)->get();
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
        $pointLedger = new PointLedger;
        $pointLedger->user_id = $user->id;
        $pointLedger->pointable_id = $pointable->id;
        $pointLedger->pointable_type = get_class($pointable);
        $pointLedger->title = $title;
        $pointLedger->amount = $amount;
        $pointLedger->debit = true;
        $pointLedger->balance = $user->point_balance - $amount;
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
        $pointLedger = new PointLedger;
        $pointLedger->user_id = $user->id;
        $pointLedger->pointable_id = $pointable->id;
        $pointLedger->pointable_type = get_class($pointable);
        $pointLedger->title = $title;
        $pointLedger->amount = $amount;
        $pointLedger->credit = true;
        $pointLedger->balance = $user->point_balance + $amount;
        $pointLedger->remarks = $remarks;
        $pointLedger->save();

        return $pointLedger;
    }
}