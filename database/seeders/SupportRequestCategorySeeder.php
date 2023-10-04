<?php

namespace Database\Seeders;

use App\Models\SupportRequestCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SupportRequestCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        SupportRequestCategory::create([
            'name' => '笔记',
            'type' => 'complain',
            'status' => 1,
            'description' => '笔记相关问题',
        ]);

        SupportRequestCategory::create([
            'name' => '账号',
            'type' => 'complain',
            'status' => 1,
            'description' => '账号相关问题',
        ]);

        // bugs
        SupportRequestCategory::create([
            'name' => '账号与安全',
            'type' => 'bug',
            'status' => 1,
            'description' => '账号与安全相关问题',
        ]);

        SupportRequestCategory::create([
            'name' => '笔记与留言',
            'type' => 'bug',
            'status' => 1,
            'description' => '笔记与留言相关问题',
        ]);

        SupportRequestCategory::create([
            'name' => '饭盒积分',
            'type' => 'bug',
            'status' => 1,
            'description' => '饭盒积分相关问题',
        ]);
        SupportRequestCategory::create([
            'name' => '优惠券',
            'type' => 'bug',
            'status' => 1,
            'description' => '优惠券相关问题',
        ]);
        SupportRequestCategory::create([
            'name' => '其他',
            'type' => 'bug',
            'status' => 1,
            'description' => '其他相关问题',
        ]);

        // feature requests
        SupportRequestCategory::create([
            'name' => '账号与安全',
            'type' => 'feature_request',
            'status' => 1,
            'description' => '账号与安全相关建议',
        ]);

        SupportRequestCategory::create([
            'name' => '笔记与留言',
            'type' => 'feature_request',
            'status' => 1,
            'description' => '笔记与留言相关建议',
        ]);

        SupportRequestCategory::create([
            'name' => '饭盒积分',
            'type' => 'feature_request',
            'status' => 1,
            'description' => '饭盒积分相关建议',
        ]);

        SupportRequestCategory::create([
            'name' => '优惠券',
            'type' => 'feature_request',
            'status' => 1,
            'description' => '优惠券相关建议',
        ]);

        SupportRequestCategory::create([
            'name' => '其他',
            'type' => 'feature_request',
            'status' => 1,
            'description' => '其他相关建议',
        ]);

    }
}
