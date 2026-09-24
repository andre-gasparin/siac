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
        Schema::create('spreadsheet_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('config');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'is_active']);
            $table->index(['team_id', 'name']);
        });

        Schema::create('spreadsheet_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('spreadsheet_template_id')->nullable()->constrained('spreadsheet_templates')->nullOnDelete();
            $table->string('file_name');
            $table->string('file_path')->nullable();
            $table->date('reference_date')->nullable();
            $table->string('status')->default('completed');
            $table->unsignedInteger('saved_values_count')->default(0);
            $table->json('snapshot')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->foreignId('reverted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['team_id', 'created_at']);
            $table->index(['team_id', 'spreadsheet_template_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spreadsheet_import_batches');
        Schema::dropIfExists('spreadsheet_templates');
    }
};
