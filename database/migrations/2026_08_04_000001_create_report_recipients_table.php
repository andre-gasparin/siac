<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->text('access_token');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->unsignedInteger('send_count')->default(0);
            $table->timestamps();

            $table->unique(['report_id', 'email']);
            $table->index(['team_id', 'report_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_recipients');
    }
};
