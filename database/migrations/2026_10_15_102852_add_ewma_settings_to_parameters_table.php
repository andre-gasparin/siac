<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parameters', function (Blueprint $table): void {
            $table->double('ewma_lambda')->default(0.2);
            $table->double('ewma_level')->default(3);
        });
    }

    public function down(): void
    {
        Schema::table('parameters', function (Blueprint $table): void {
            $table->dropColumn(['ewma_lambda', 'ewma_level']);
        });
    }
};
