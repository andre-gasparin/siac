<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parameter_value_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitored_system_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parameter_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('measured_at');
            $table->string('action');
            $table->json('changes');
            $table->timestamps();
            $table->index(['team_id', 'measured_at', 'monitored_system_id'], 'pvr_team_time_system_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parameter_value_revisions');
    }
};
