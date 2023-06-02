<?php
namespace App\Services;

use App\Models\PointComponentLedger;

class PointComponentService
{
    public function getPointComponentLedger($user)
    {
        return PointComponentLedger::where('user_id', $user->id)->get();
    }

    /**
     * Debit point
     * 
     * @param $pointable
     * @param $type
     * @param $user
     * @param $amount
     * @param $title
     * @param $remarks
     * @return PointComponentLedger
     */
    public function debit($pointable, $type, $user, $amount, $title, $remarks = null) : PointComponentLedger
    {
        $pointLedger = new PointComponentLedger;
        $pointLedger->user_id = $user->id;
        $pointLedger->pointable_id = $pointable->id;
        $pointLedger->pointable_type = get_class($pointable);
        $pointLedger->component_type = get_class($type);
        $pointLedger->component_id = $type->id;
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
     * @param $type
     * @param $user
     * @param $amount
     * @param $title
     * @param $remarks
     * @return PointComponentLedger
     */
    public function credit($pointable, $type, $user, $amount, $title, $remarks = null) : PointComponentLedger
    {
        $pointLedger = new PointComponentLedger;
        $pointLedger->user_id = $user->id;
        $pointLedger->pointable_id = $pointable->id;
        $pointLedger->pointable_type = get_class($pointable);
        $pointLedger->component_type = get_class($type);
        $pointLedger->component_id = $type->id;
        $pointLedger->title = $title;
        $pointLedger->amount = $amount;
        $pointLedger->credit = true;
        $pointLedger->balance = $user->point_balance + $amount;
        $pointLedger->remarks = $remarks;
        $pointLedger->save();

        return $pointLedger;
    }
}