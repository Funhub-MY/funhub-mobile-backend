<?php

namespace Database\Factories;

use App\Models\UserCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserCardFactory extends Factory
{
    protected $model = UserCard::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'card_type' => $this->faker->randomElement(['visa', 'mastercard', 'amex', 'discover']),
            'card_last_four' => $this->faker->numerify('####'),
            'card_holder_name' => $this->faker->name(),
            'card_expiry_month' => $this->faker->numberBetween(1, 12),
            'card_expiry_year' => $this->faker->numberBetween(date('Y'), date('Y') + 10),
            'card_token' => Str::random(64),
            'is_default' => $this->faker->boolean(20),
        ];
    }

    /**
     * Indicate that the card is expired.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function expired()
    {
        return $this->state(function (array $attributes) {
            return [
                'card_expiry_month' => $this->faker->numberBetween(1, 12),
                'card_expiry_year' => $this->faker->numberBetween(date('Y') - 5, date('Y') - 1),
            ];
        });
    }

    /**
     * Indicate that the card is the default card.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function default()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_default' => true,
            ];
        });
    }
}
