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

        // generate random events and values
        $events = $this->faker->randomElements($keys, $this->faker->numberBetween(1, count($keys)));
        $values = [];
        foreach ($events as $event) {
            $values[$event] = $this->faker->numberBetween(1, 10);
        }

        return [
            'name' => $this->faker->name,
            'description' => 'Mission 1',
            'enabled' => $this->faker->boolean,
            'events' => json_encode($events),
            'values' => json_encode($values),
            'frequency' => $this->faker->randomElement(['one-off', 'daily', 'monthly']),
            'missionable_type' => get_class($this->faker->randomElement($components)),
            'missionable_id' => $this->faker->randomElement($components)->id,
            'reward_quantity' => $this->faker->numberBetween(1, 10),
        ];
    }
}
