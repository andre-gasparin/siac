<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('upload_import_issues');
        Schema::dropIfExists('upload_batches');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data removed by this migration must be restored from its pre-deployment backup.
    }
};
