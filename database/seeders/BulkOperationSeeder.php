<?php

namespace Database\Seeders;

use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BulkOperationSeeder extends Seeder
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

        // Create a large set of tasks for bulk operation testing
        $bulkTasks = [];
        for ($i = 1; $i <= 50; $i++) {
            $bulkTasks[] = [
                'title' => "Bulk Task {$i}",
                'description' => "Description for bulk task {$i}",
                'status' => array_rand(['pending' => 1, 'in_progress' => 1, 'completed' => 1]),
                'priority' => array_rand(['low' => 1, 'medium' => 1, 'high' => 1]),
                'due_date' => now()->addDays(rand(1, 30)),
                'user_id' => $adminUser->id,
                'assigned_to' => rand(0, 1) ? $adminUser->id : $regularUser->id,
                'metadata' => json_encode(['bulk_created' => true, 'batch' => 'bulk_operations']),
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert all bulk tasks using DB facade to avoid model casting issues
        foreach (array_chunk($bulkTasks, 500) as $chunk) {
            DB::table('tasks')->insert($chunk);
        }

        // Attach random tags to bulk tasks
        $bulkTasks = Task::where('metadata->bulk_created', true)->get();
        foreach ($bulkTasks as $task) {
            $randomTags = $tags->random(rand(1, 3))->pluck('id')->toArray();
            $task->tags()->attach($randomTags);
        }
    }
}
