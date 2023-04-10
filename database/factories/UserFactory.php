<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'otp_verified_at' => now(),
            'phone_no' => fake()->randomNumber(9),
            'phone_country_code' => '60',
            'bio' => fake()->paragraph(1),
            'gender' => fake()->randomElement(['male', 'female']),
            'job_title' => fake()->jobTitle(),
            'password' => Hash::make('abcd1234'), // password
            'remember_token' => Str::random(10),
        ];
    }

    public function otpVerified()
    {
        return $this->state(fn (array $attributes) => [
            'otp_verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function unverified()
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
