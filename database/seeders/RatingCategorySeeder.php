<?php

namespace Database\Seeders;

use App\Models\RatingCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RatingCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        RatingCategory::create([
            'name' => '美味可口',
            'name_translations' => json_encode([
                'en' => 'Delicious',
                'zh' => '美味可口'
            ])
        ]);

        RatingCategory::create([
            'name' => '优质服务',
            'name_translations' => json_encode([
                'en' => 'Good Service',
                'zh' => '优质服务'
            ])
        ]);

        RatingCategory::create([
            'name' => '价钱公道',
            'name_translations' => json_encode([
                'en' => 'Reasonable Price',
                'zh' => '价钱公道'
            ])
        ]);

        RatingCategory::create([
            'name' => '服务周到',
            'name_translations' => json_encode([
                'en' => 'Attentive Service',
                'zh' => '服务周到'
            ])
        ]);

        RatingCategory::create([
            'name' => '卫生洁净',
            'name_translations' => json_encode([
                'en' => 'Cleanliness',
                'zh' => '卫生洁净'
            ])
        ]);

        RatingCategory::create([
            'name' => '泊车方便',
            'name_translations' => json_encode([
                'en' => 'Convenient Parking',
                'zh' => '泊车方便'
            ])
        ]);
    }
}
