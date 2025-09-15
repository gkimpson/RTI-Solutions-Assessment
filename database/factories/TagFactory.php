<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Urgent', 'Bug', 'Feature', 'Enhancement', 'Documentation',
                'Testing', 'Backend', 'Frontend', 'API', 'Database',
                'Security', 'Performance', 'UI/UX', 'Mobile', 'Desktop',
            ]),
            'color' => fake()->hexColor(),
        ];
    }

    /**
     * Indicate that the tag is for urgent items.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Urgent',
            'color' => '#FF0000',
        ]);
    }

    /**
     * Indicate that the tag is for bugs.
     */
    public function bug(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Bug',
            'color' => '#DC2626',
        ]);
    }

    /**
     * Indicate that the tag is for features.
     */
    public function feature(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Feature',
            'color' => '#059669',
        ]);
    }

    /**
     * Indicate that the tag is for backend work.
     */
    public function backend(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Backend',
            'color' => '#2563EB',
        ]);
    }

    /**
     * Indicate that the tag is for frontend work.
     */
    public function frontend(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Frontend',
            'color' => '#7C3AED',
        ]);
    }
}
