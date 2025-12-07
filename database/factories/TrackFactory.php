<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TrackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'isrc'       => $this->faker->uuid(),
            'name'       => $this->faker->word(),
            'artists'    => $this->faker->name(),
            'album'      => $this->faker->word(),
            'explicit'   => $this->faker->boolean(),
            'remote_ids' => [
                'spotify' => $this->faker->uuid(),
            ],
        ];
    }
}
