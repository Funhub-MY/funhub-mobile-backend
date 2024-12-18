<?php

namespace App\Http\Resources;

use App\Models\Merchant;
use Illuminate\Http\Resources\Json\JsonResource;

class SyncMerchantResource extends JsonResource
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
            'name' => $this->name,
            'business_name' => $this->business_name,
            'company_reg_no' => $this->company_reg_no,
            'brand_name' => $this->brand_name ?? null,
            'logo' => ($this->getFirstMedia(Merchant::MEDIA_COLLECTION_NAME)) ? $this->getFirstMedia(Merchant::MEDIA_COLLECTION_NAME) : null,
            'address' => $this->address,
            'address_postcode' => $this->address_postcode,
            'default_password' => $this->default_password,
            'redeem_code' => $this->redeem_code,
            'business_phone_no' => $this->business_phone_no,
            'pic_name' => $this->pic_name,
            'pic_designation' => $this->pic_designation,
            'pic_ic_no' => $this->pic_ic_no,
            'pic_phone_no' => $this->pic_phone_no,
            'pic_email' => $this->pic_email,
            'authorised_personnel_designation' => $this->authorised_personnel_designation,
            'authorised_personnel_name' => $this->authorised_personnel_name,
            'authorised_personnel_ic_no' => $this->authorised_personnel_ic_no,
            'state' => $this->state,
            'country' => $this->country,
            'state_id' => $this->state_id,
            'country_id' => $this->country_id,
            'status' => $this->status,
            'user_id'  => $this->user_id,
            'user'  => $this->user,
            'categories' => $this->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name
                ];
            }),
            // 'ratings' => $this->ratings,
            // 'total_ratings' => $this->merchant_ratings_count,
            'stores' => $this->merchantStores->map(function ($store) {
                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'manager_name' => $store->manager_name,
                    'business_phone_no' => $store->business_phone_no,
                    'business_hours' => (object) $this->formatHours($store->business_hours),
                    'rest_hours' => (object) $this->formatHours($store->rest_hours),
                    'address' => $store->address,
                    'address_postcode' => $store->address_postcode,
                    'long' => $store->long,
                    'lang' => $store->lang,
                    'is_hq' => $store->is_hq,
                    'is_closed' => $store->is_closed,
                    'state' => $store->state,
                    'country' => $store->country,
                    'state_id' => $store->state_id,
                    'status' => $store->status,
                    'country_id' => $store->country_id,
                    'created_at' => $store->created_at,
                    'updated_at' => $store->updated_at,
                    'categories' => $store->categories->map(function ($category) {
                        return [
                            'id' => $category->id,
                            'name' => $category->name
                        ];
                    }),
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Format hours into an object-like structure.
     *
     * @param string|null $hours
     * @return array|null
     */
    protected function formatHours($hours)
    {
        if (!$hours) {
            return null;
        }

        $decoded = json_decode($hours, true);

        // Ensure the keys are retained
        return collect($decoded)->mapWithKeys(function ($hour, $day) {
            return [
                (string) $day => [
                    'open_time' => $hour['open_time'] ?? null,
                    'close_time' => $hour['close_time'] ?? null,
                ]
            ];
        })->toArray();
    }
}
