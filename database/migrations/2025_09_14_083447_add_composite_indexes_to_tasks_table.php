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
            // Composite indexes for common query patterns
            $table->index(['assigned_to', 'status'], 'tasks_assigned_to_status_index');
            $table->index(['assigned_to', 'priority'], 'tasks_assigned_to_priority_index');
            $table->index(['status', 'priority'], 'tasks_status_priority_index');
            $table->index(['due_date', 'status'], 'tasks_due_date_status_index');
            $table->index(['created_at', 'assigned_to'], 'tasks_created_at_assigned_to_index');

            // Full-text search index for title and description (MySQL only)
            // This would need to be adapted for other database systems
            if (config('database.default') === 'mysql') {
                DB::statement('ALTER TABLE tasks ADD FULLTEXT tasks_search_index (title, description)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_assigned_to_status_index');
            $table->dropIndex('tasks_assigned_to_priority_index');
            $table->dropIndex('tasks_status_priority_index');
            $table->dropIndex('tasks_due_date_status_index');
            $table->dropIndex('tasks_created_at_assigned_to_index');

            // Drop full-text search index (MySQL only)
            if (config('database.default') === 'mysql') {
                DB::statement('ALTER TABLE tasks DROP INDEX tasks_search_index');
            }
        });
    }
};
