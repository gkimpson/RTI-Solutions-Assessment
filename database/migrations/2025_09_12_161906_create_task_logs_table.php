<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('task_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('operation_type', ['create', 'update', 'delete', 'restore', 'toggle_status']);
            $table->json('changes')->nullable(); // Store the actual changes made
            $table->json('old_values')->nullable(); // Store old values for update operations
            $table->json('new_values')->nullable(); // Store new values for update operations
            $table->timestamp('performed_at');
            $table->timestamps();

            $table->index('task_id');
            $table->index('user_id');
            $table->index('operation_type');
            $table->index('performed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_logs');
    }
};
