<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductHistoryResource extends JsonResource
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
            'funbox' => $this->point_amount,
            'transactionable' => [
                'id' => $this->transactionable->id,
                'type' => $this->transactionable->type,
                'name' => $this->transactionable->name,
                'slug' => $this->transactionable->slug,
                'description' => $this->transactionable->description,
                'campaign_url' => $this->transactionable->campaign_url ?? null,
                'unit_price' => $this->transactionable->unit_price,
                'discount_price' => $this->transactionable->discount_price ?? null,
                'thumbnail' => $this->transactionable->thumbnail
            ],
            'gateway' => $this->gateway,
            'payment_method' => $this->payment_method,            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->updated_at->diffForHumans(),
        ];

        // return [
        //     'id' => $this->id,
		// 	'order' => $this->order,
        //     'type' => $this->type,
        //     'name' => $this->name,
        //     'slug' => $this->slug,
        //     'description' => $this->description,
        //     'campaign_url' => $this->campaign_url ?? null,
        //     'unit_price' => $this->unit_price,
        //     'discount_price' => $this->discount_price,
        //     'unlimited_supply' => $this->unlimited_supply,
        //     'quantity' => $this->quantity,
        //     'reward' => ($rewards) ? $rewards->toArray() : null,
        //     'status' => $this->status,
        //     'thumbnail' => $this->thumbnail,
        //     'created_at' => $this->created_at,
        //     'updated_at' => $this->updated_at,
        // ];
    }
}
