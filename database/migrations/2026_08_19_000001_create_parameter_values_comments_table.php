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
        Schema::create('parameter_values_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitored_system_id')->constrained('monitored_systems')->cascadeOnDelete();
            $table->timestamp('measured_at');
            $table->date('measured_date');
            $table->text('comment');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['team_id', 'monitored_system_id', 'measured_at'], 'pvc_team_system_time_idx');
            $table->index(['team_id', 'measured_date'], 'pvc_team_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parameter_values_comments');
    }
};
