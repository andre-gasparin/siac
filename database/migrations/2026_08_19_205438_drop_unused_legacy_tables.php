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
        Schema::table('parameter_values', function (Blueprint $table): void {
            $table->dropForeign(['upload_batch_id']);
        });

        Schema::table('parameter_values', function (Blueprint $table): void {
            $table->dropIndex('pv_upload_batch_idx');
        });

        Schema::table('parameter_values', function (Blueprint $table): void {
            $table->dropColumn('upload_batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The legacy upload reference cannot be restored without its removed parent tables.
    }
};
