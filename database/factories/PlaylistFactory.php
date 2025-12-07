<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PlaylistFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'      => $this->faker->sentence(3),
            'provider'  => 'spotify',
            'remote_id' => $this->faker->uuid(),
        ];
    }
}
