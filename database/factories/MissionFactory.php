<?php

namespace Database\Factories;

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
    public function definition(): array
    {
        $categories = ['saude', 'mente', 'corpo', 'disciplina', 'financas'];

        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(),
            'xp' => $this->faker->numberBetween(10, 50),
            'category' => $this->faker->randomElement($categories)
        ];
    }
}
