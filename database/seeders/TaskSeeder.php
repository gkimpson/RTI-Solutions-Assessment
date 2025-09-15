<?php

namespace Database\Seeders;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get users - they should already be created by UserSeeder
        $adminUser = User::where('email', 'admin@example.com')->first();
        $regularUser = User::where('email', 'user1@example.com')->first();

        if (! $adminUser || ! $regularUser) {
            echo 'Required users not found. Please run UserSeeder first.\\n';

            return;
        }

        // Create some sample tags
        $urgentTag = Tag::firstOrCreate(
            ['name' => 'urgent'],
            ['color' => '#ff4444']
        );

        $featureTag = Tag::firstOrCreate(
            ['name' => 'feature'],
            ['color' => '#4444ff']
        );

        $bugTag = Tag::firstOrCreate(
            ['name' => 'bug'],
            ['color' => '#ff8844']
        );

        // Create sample tasks
        $task1 = Task::create([
            'title' => 'Implement user authentication system',
            'description' => 'Create secure user authentication with proper validation and session management.',
            'status' => TaskStatus::InProgress,
            'priority' => TaskPriority::High,
            'due_date' => now()->addWeeks(2),
            'user_id' => $adminUser->id, // Assign to admin as creator
            'assigned_to' => $regularUser->id,
            'metadata' => ['estimated_hours' => 16, 'complexity' => 'high'],
            'version' => 1,
        ]);

        $task2 = Task::create([
            'title' => 'Fix navigation menu responsive issues',
            'description' => 'The navigation menu is not displaying correctly on mobile devices.',
            'status' => TaskStatus::Pending,
            'priority' => TaskPriority::Medium,
            'due_date' => now()->addDays(5),
            'user_id' => $regularUser->id, // Assign to regular user as creator
            'assigned_to' => $regularUser->id,
            'metadata' => ['estimated_hours' => 4, 'complexity' => 'low'],
            'version' => 1,
        ]);

        $task3 = Task::create([
            'title' => 'Database optimization and indexing',
            'description' => 'Optimize database queries and add proper indexing for better performance.',
            'status' => TaskStatus::Completed,
            'priority' => TaskPriority::Low,
            'due_date' => now()->subDays(2),
            'user_id' => $adminUser->id, // Assign to admin as creator
            'metadata' => ['estimated_hours' => 8, 'complexity' => 'medium'],
            'version' => 1,
        ]);

        // Attach tags to tasks
        $task1->tags()->attach([$featureTag->id, $urgentTag->id]);
        $task2->tags()->attach([$bugTag->id]);
        $task3->tags()->attach([$featureTag->id]);
    }
}
