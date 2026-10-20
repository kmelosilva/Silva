<?php

namespace Database\Factories;

use App\Models\Mission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Coordinate>
 */
class CoordinateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mission_id' => Mission::factory(),
            'slug' => $this->faker->unique()->slug(3),
            'position' => 1,
            'title' => $this->faker->sentence(4),
            'content' => $this->faker->paragraph(),
            'xp' => 20,
        ];
    }
}
