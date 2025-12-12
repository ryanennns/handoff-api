<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PlaylistTransferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'source'              => $this->faker->word,
            'destination'         => $this->faker->word,
            'status'              => $this->faker->randomElement(['pending', 'completed', 'failed']),
            'playlists_processed' => 0
        ];
    }
}
