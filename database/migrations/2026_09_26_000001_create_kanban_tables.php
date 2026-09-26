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
        Schema::create('kanban_boards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('kanban_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kanban_board_id')->constrained('kanban_boards')->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 30)->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        Schema::create('kanban_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kanban_board_id')->constrained('kanban_boards')->cascadeOnDelete();
            $table->foreignId('kanban_column_id')->constrained('kanban_columns')->cascadeOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        Schema::create('kanban_task_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kanban_task_id')->constrained('kanban_tasks')->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_completed')->default(false);
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        Schema::create('kanban_task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kanban_task_id')->constrained('kanban_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kanban_task_attachments');
        Schema::dropIfExists('kanban_task_checklists');
        Schema::dropIfExists('kanban_tasks');
        Schema::dropIfExists('kanban_columns');
        Schema::dropIfExists('kanban_boards');
    }
};
