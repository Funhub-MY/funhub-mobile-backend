<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
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
            'transaction_no' => $this->transaction_no,
            'amount' => $this->amount,
            'transactionable' => [
                'id' => $this->transactionable_id,
                'type' => $this->transactionable_type,
            ],
            'gateway' => $this->gateway,
            'payment_method' => $this->payment_method,
            'created_at' => $this->created_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->updated_at->diffForHumans(),
        ];
    }
}
