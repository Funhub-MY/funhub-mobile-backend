<?php

namespace App\Http\Resources;

use App\Models\MerchantOffer;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class PointLedgerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->user),
            'reason' => $this->getReason(),
            'credit' => ($this->credit) ? $this->amount : 0,
            'debit' => ($this->debit) ? $this->amount : 0,
            'balance' => $this->balance,
            'created_at' => $this->created_at,
            'created_at_formatted' => $this->created_at->format('d/m/Y'),
            'created_at_ago' => $this->created_at->diffForHumans(),
            'updated_at' => $this->updated_at,
            'updated_at_formatted' => $this->updated_at->format('d/m/Y'),
            'updated_at_ago' => $this->updated_at->diffForHumans(),
        ];
    }

    private function getReason()
    {
        if ($this->pointable_type === MerchantOffer::class) {
            return _('Used to purchase deal :').$this->pointable->name;
        } else if ($this->pointable_type === Reward::class && $this->title == 'Reward Formed') {
            return _('Combined a reward :').$this->pointable->name;
        } else {
            return $this->title;
        }
    }
}
