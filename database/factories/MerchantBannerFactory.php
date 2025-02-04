<?php

namespace Database\Factories;

use App\Models\MerchantBanner;
use Illuminate\Database\Eloquent\Factories\Factory;

class MerchantBannerFactory extends Factory
{
    protected $model = MerchantBanner::class;

    public function definition()
    {
        return [
            'title' => $this->faker->sentence(3),
            'link_to' => $this->faker->url,
            'status' => MerchantBanner::STATUS_DRAFT,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function published()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => MerchantBanner::STATUS_PUBLISHED,
            ];
        });
    }
}
