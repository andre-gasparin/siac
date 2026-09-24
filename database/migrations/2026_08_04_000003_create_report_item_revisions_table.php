<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_item_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->default('manual');
            $table->json('document')->nullable();
            $table->longText('html')->nullable();
            $table->boolean('show_data_results')->default(false);
            $table->boolean('hide_data')->default(false);
            $table->boolean('is_stopped')->default(false);
            $table->timestamps();

            $table->index(['report_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_item_revisions');
    }
};
