<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskLog>
 */
class TaskLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'action' => fake()->randomElement(['create', 'update', 'delete', 'restore']),
            'changes' => [
                'title' => [
                    'from' => fake()->sentence(3),
                    'to' => fake()->sentence(3),
                ],
                'status' => [
                    'from' => 'pending',
                    'to' => 'in_progress',
                ],
            ],
        ];
    }

    /**
     * Indicate that the log is for a create action.
     *
     * @param  array  $attributes
     */
    public function create($attributes = [], ?Model $parent = null): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'create',
            'changes' => [
                'created' => true,
            ],
        ]);
    }

    /**
     * Indicate that the log is for an update action.
     */
    public function update(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'update',
        ]);
    }

    /**
     * Indicate that the log is for a delete action.
     */
    public function delete(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'delete',
            'changes' => [
                'deleted' => true,
            ],
        ]);
    }

    /**
     * Indicate that the log is for a restore action.
     */
    public function restore(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'restore',
            'changes' => [
                'restored' => true,
            ],
        ]);
    }

    /**
     * Indicate that the log is for a bulk operation.
     */
    public function bulk(): static
    {
        return $this->state(fn (array $attributes) => [
            'changes' => array_merge($attributes['changes'] ?? [], [
                'bulk_operation' => true,
            ]),
        ]);
    }
}
