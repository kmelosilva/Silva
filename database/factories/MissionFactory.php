<?php

namespace Database\Factories;

use App\Enums\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Só para testes: dado inventado não entra no seed do projeto.
 *
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
        return [
            'slug' => $this->faker->unique()->slug(3),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(),
            'category' => $this->faker->randomElement(Category::cases()),
            'is_entry' => false,
            'position' => 0,
        ];
    }

    /**
     * Como as missões que o gerador criava antes do catálogo existir: sem slug.
     */
    public function generated(): static
    {
        return $this->state(fn () => ['slug' => null]);
    }
}
