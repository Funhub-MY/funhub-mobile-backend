<?php
namespace App\Services;

use App\Models\PointComponentLedger;
use Illuminate\Support\Facades\Log;

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
        $pointLedger->balance = $this->getBalanceByComponent($user, $type) - $amount;
        $pointLedger->remarks = $remarks;
        $pointLedger->save();

        return $pointLedger;
    }

    /**
     * Credit point
     * 
     * @param $pointable    Object that causes this credit
     * @param $type         Type of point component credited    
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
        $pointLedger->balance = $this->getBalanceByComponent($user, $type) + $amount;
        $pointLedger->remarks = $remarks;
        $pointLedger->save();

        return $pointLedger;
    }

    public function getBalanceByComponent($user, $component)
    {
        $latest = PointComponentLedger::where('user_id', $user->id)
            ->where('component_type', get_class($component))
            ->where('component_id', $component->id)
            ->orderBy('id', 'desc')
            ->first();

        if(!$latest) {
            Log::info('No latest point component ledger found for user '.$user->id.' and component '.$component->id);
        }

        return $latest ? $latest->balance : 0;
    }
}