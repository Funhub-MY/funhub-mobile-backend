<?php

namespace Database\Factories;

use App\Models\RewardComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Mission>
 */
class MissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        // get random reward component
        $components = RewardComponent::all();
        
        // get keys from array
        $keys = array_keys(config('app.event_matrix'));
        return [
            'name' => $this->faker->name,
            'description' => 'Mission 1',
            'event' => $this->faker->randomElement($keys),
            'value' => $this->faker->numberBetween(1, 10),
            'missionable_type' => get_class($this->faker->randomElement($components)),
            'missionable_id' => $this->faker->randomElement($components)->id,
            'reward_quantity' => $this->faker->numberBetween(1, 10),
        ];
    }
}
