<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OauthCredentialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider'      => $this->faker->randomElement(['spotify', 'youtube', 'tidal']),
            'provider_id'   => $this->faker->uuid(),
            'email'         => $this->faker->unique()->safeEmail(),
            'token'         => $this->faker->sha256(),
            'refresh_token' => $this->faker->sha256(),
            'expires_at'    => $this->faker->dateTimeBetween('now', '+1 year'),
        ];
    }
}
