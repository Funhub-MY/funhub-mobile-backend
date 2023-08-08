<?php

namespace Database\Factories;

use App\Models\State;
use Faker\Provider\ms_MY\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $this->faker->addProvider(new Address($this->faker));
        $states = State::all();
        $stateIds = $states->pluck('id')->toArray();

        $selectedState = $this->faker->randomElement($stateIds);
        $state = $states->where('id', $selectedState)->first();

        return [
            'name' => $this->faker->company(),
            'address' => $this->faker->address(),
            'zip_code' => $this->faker->postcode(),
            'city' => $this->faker->township(),
            'state_id' => $selectedState,
            'country_id' => $state->country_id, // malaysia id
            'phone_no' => $this->faker->phoneNumber(),
            'location' => [
                'lat' => $this->faker->latitude(),
                'lng' => $this->faker->longitude(),
            ],
        ];
    }

    /**
     * Published location where status = 1
     */
    public function published()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 1,
            ];
        });
    }

    /**
     * Draft location where status = 0
     */
    public function draft()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 0,
            ];
        });
    }
}
