<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Performance index for soft delete queries
            if (! Schema::hasIndex('tasks', 'tasks_deleted_at_index')) {
                $table->index(['deleted_at'], 'tasks_deleted_at_index');
            }

            // Compound index for overdue task filtering
            if (! Schema::hasIndex('tasks', 'tasks_overdue_compound_index')) {
                $table->index(['due_date', 'status', 'deleted_at'], 'tasks_overdue_compound_index');
            }

            // Index for user assignment with status filtering
            if (! Schema::hasIndex('tasks', 'tasks_assigned_to_active_index')) {
                $table->index(['assigned_to', 'deleted_at'], 'tasks_assigned_to_active_index');
            }
        });

        // Add metadata index for JSON queries (MySQL only)
        if (config('database.default') === 'mysql') {
            try {
                DB::statement('ALTER TABLE tasks ADD INDEX tasks_metadata_index ((CAST(metadata AS CHAR(255))))');
            } catch (\Exception $e) {
                // Index might already exist, ignore error
            }
        }

        Schema::table('task_logs', function (Blueprint $table) {
            // Performance indexes for audit log queries (check if they don't already exist)
            if (! Schema::hasIndex('task_logs', 'task_logs_task_timeline_index')) {
                $table->index(['task_id', 'created_at'], 'task_logs_task_timeline_index');
            }
            if (! Schema::hasIndex('task_logs', 'task_logs_user_activity_index')) {
                $table->index(['user_id', 'created_at'], 'task_logs_user_activity_index');
            }
            if (! Schema::hasIndex('task_logs', 'task_logs_operation_timeline_index')) {
                $table->index(['operation_type', 'created_at'], 'task_logs_operation_timeline_index');
            }
        });

        Schema::table('task_tag', function (Blueprint $table) {
            // Optimize many-to-many relationship queries
            if (! Schema::hasIndex('task_tag', 'task_tag_reverse_index')) {
                $table->index(['tag_id', 'task_id'], 'task_tag_reverse_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_deleted_at_index');
            $table->dropIndex('tasks_overdue_compound_index');
            $table->dropIndex('tasks_assigned_to_active_index');
        });

        Schema::table('task_logs', function (Blueprint $table) {
            $table->dropIndex('task_logs_task_timeline_index');
            $table->dropIndex('task_logs_user_activity_index');
            $table->dropIndex('task_logs_operation_timeline_index');
        });

        Schema::table('task_tag', function (Blueprint $table) {
            $table->dropIndex('task_tag_reverse_index');
        });

        // Drop metadata index if it exists
        if (config('database.default') === 'mysql') {
            DB::statement('DROP INDEX IF EXISTS tasks_metadata_index ON tasks');
        }
    }
};
