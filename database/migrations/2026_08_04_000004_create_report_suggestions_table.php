<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode');
            $table->string('status')->default('pending');
            $table->text('original_text');
            $table->text('replacement_text');
            $table->text('reason')->nullable();
            $table->timestamp('base_updated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['report_id', 'status', 'created_at']);
            $table->index(['report_item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_suggestions');
    }
};
