<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\TaskLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get all tasks and users
        $tasks = Task::all();
        $users = User::all();

        if ($tasks->isEmpty() || $users->isEmpty()) {
            echo "No tasks or users found. Please run TaskSeeder and UserSeeder first.\n";

            return;
        }

        // Create sample task logs for each task
        foreach ($tasks as $task) {
            // Get a random user for the log
            $user = $users->random();

            // Create initial creation log
            TaskLog::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'operation_type' => TaskLog::OPERATION_CREATE,
                'changes' => [],
                'old_values' => [],
                'new_values' => $task->toArray(),
                'performed_at' => $task->created_at,
            ]);

            // Create update logs if the task has been updated
            if ($task->updated_at && $task->updated_at != $task->created_at) {
                TaskLog::create([
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                    'operation_type' => TaskLog::OPERATION_UPDATE,
                    'changes' => ['title' => 'Updated task title'],
                    'old_values' => ['title' => 'Old task title'],
                    'new_values' => ['title' => $task->title],
                    'performed_at' => $task->updated_at,
                ]);
            }

            // Create status toggle logs for completed tasks
            if ($task->status === 'completed') {
                TaskLog::create([
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                    'operation_type' => TaskLog::OPERATION_TOGGLE_STATUS,
                    'changes' => ['status' => ['from' => 'in_progress', 'to' => 'completed']],
                    'old_values' => ['status' => 'in_progress'],
                    'new_values' => ['status' => 'completed'],
                    'performed_at' => $task->updated_at ?? now(),
                ]);
            }
        }
    }
}
