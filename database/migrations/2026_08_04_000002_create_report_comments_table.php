<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('report_recipient_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['report_id', 'report_item_id', 'created_at']);
            $table->index(['team_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_comments');
    }
};
