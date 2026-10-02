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
        Schema::table('data_entry_batches', function (Blueprint $table): void {
            $table->string('batch_group_uuid', 36)->nullable()->after('monitored_system_id');
            $table->string('responsible', 255)->nullable()->after('user_id');

            $table->index('batch_group_uuid');
            $table->index(['team_id', 'batch_group_uuid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_entry_batches', function (Blueprint $table): void {
            $table->dropIndex(['team_id', 'batch_group_uuid']);
            $table->dropIndex(['batch_group_uuid']);
            $table->dropColumn(['batch_group_uuid', 'responsible']);
        });
    }
};
