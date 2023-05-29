<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Reward;
use App\Models\RewardComponent;

class RewardAndRewardComponentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // admin user id
        $admin = User::where('email', 'admin@funhub.my')->first();

        $reward = Reward::create([
            'name' => '饭盒FUNHUB',
            'description' => '饭盒FUNHUB',
            'points' => 1, // current 1 reward is 1 of value
            'user_id' => $admin->id
        ]);

        if ($reward) {
            $components = [
                ['name' => '鸡蛋', 'description' => '鸡蛋'],
                ['name' => '蔬菜', 'description' => '蔬菜'],
                ['name' => '饭', 'description' => '饭'],
                ['name' => '肉', 'description' => '肉'],
                ['name' => '盒子', 'description' => '盒子'],
            ];

            foreach ($components as $component) {
                $rewardComponent = RewardComponent::create([
                    'name' => $component['name'],
                    'description' => $component['description'],
                    'user_id' => $reward->user_id,
                ]);

                $reward->rewardComponents()->attach($rewardComponent->id, ['points' => 5]);
            }
        }
    }
}
