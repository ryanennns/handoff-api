<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PlaylistTransferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'source'      => $this->faker->word,
            'destination' => $this->faker->word,
            'playlists'   => [['id' => $this->faker->uuid, 'name' => $this->faker->sentence]],
            'status'      => $this->faker->randomElement(['pending', 'completed', 'failed']),
        ];
    }
}
