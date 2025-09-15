<?php

namespace Database\Seeders;

use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class EdgeCaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get users
        $adminUser = User::where('email', 'admin@example.com')->first();
        $regularUser = User::where('email', 'user1@example.com')->first();

        if (! $adminUser || ! $regularUser) {
            echo "Required users not found. Please run UserSeeder first.\n";

            return;
        }

        // Get tags
        $tags = Tag::all();

        if ($tags->isEmpty()) {
            echo "No tags found. Please run TagSeeder first.\n";

            return;
        }

        // Create tasks with edge cases for testing

        // 1. Task with past due date
        $pastDueTask = Task::create([
            'title' => 'Past Due Task',
            'description' => 'This task has a due date in the past',
            'status' => 'pending',
            'priority' => 'high',
            'due_date' => Carbon::now()->subDays(5),
            'user_id' => $adminUser->id,
            'assigned_to' => $regularUser->id,
            'metadata' => ['edge_case' => 'past_due'],
            'version' => 1,
        ]);

        // 2. Task with complex metadata
        $complexMetadataTask = Task::create([
            'title' => 'Complex Metadata Task',
            'description' => 'This task has complex nested metadata',
            'status' => 'in_progress',
            'priority' => 'medium',
            'due_date' => Carbon::now()->addWeeks(2),
            'user_id' => $adminUser->id,
            'assigned_to' => $adminUser->id,
            'metadata' => [
                'estimated_hours' => 20,
                'dependencies' => ['task-1', 'task-2'],
                'reviewers' => ['user1@example.com', 'user2@example.com'],
                'tags' => ['urgent', 'feature'],
                'nested' => [
                    'level1' => [
                        'level2' => 'deep_value',
                    ],
                ],
            ],
            'version' => 1,
        ]);

        // 3. Task with many tags
        $manyTagsTask = Task::create([
            'title' => 'Many Tags Task',
            'description' => 'This task has many tags attached',
            'status' => 'pending',
            'priority' => 'low',
            'due_date' => Carbon::now()->addMonth(),
            'user_id' => $regularUser->id,
            'assigned_to' => $regularUser->id,
            'metadata' => ['edge_case' => 'many_tags'],
            'version' => 1,
        ]);

        // 4. Task with special characters in title and description
        $specialCharsTask = Task::create([
            'title' => 'Special Ch@rs & Ünicode Täsk',
            'description' => 'This task has special characters: @#$%^&*()_+{}|:"<>?~` and unicode: ñáéíóúü',
            'status' => 'pending',
            'priority' => 'medium',
            'due_date' => Carbon::now()->addDays(10),
            'user_id' => $regularUser->id,
            'assigned_to' => $adminUser->id,
            'metadata' => ['edge_case' => 'special_chars'],
            'version' => 1,
        ]);

        // 5. Task with long title and description (but within limits)
        $longTextTask = Task::create([
            'title' => str_repeat('Long Title ', 20), // 200 characters
            'description' => str_repeat('Long description text. ', 50), // 1050 characters
            'status' => 'pending',
            'priority' => 'low',
            'due_date' => Carbon::now()->addWeeks(3),
            'user_id' => $adminUser->id,
            'assigned_to' => $regularUser->id,
            'metadata' => ['edge_case' => 'long_text'],
            'version' => 1,
        ]);

        // Attach tags to the many tags task
        $manyTagsTask->tags()->attach($tags->pluck('id')->toArray());

        // Attach some tags to other tasks
        $pastDueTask->tags()->attach([$tags->first()->id]);
        $complexMetadataTask->tags()->attach([$tags->get(1)->id, $tags->get(2)->id]);
        $specialCharsTask->tags()->attach([$tags->last()->id]);
        $longTextTask->tags()->attach([$tags->get(3)->id, $tags->get(4)->id]);
    }
}
