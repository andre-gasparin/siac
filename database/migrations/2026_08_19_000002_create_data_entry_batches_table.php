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
        Schema::create('data_entry_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('monitored_system_id')->constrained('monitored_systems')->cascadeOnDelete();
            $table->timestamp('collected_at');
            $table->date('collected_date');
            $table->string('status')->default('completed')->index();
            $table->unsignedInteger('saved_values_count')->default(0);
            $table->json('parameter_ids')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->foreignId('reverted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'created_at']);
            $table->index(['team_id', 'monitored_system_id']);
            $table->index(['team_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_entry_batches');
    }
};
