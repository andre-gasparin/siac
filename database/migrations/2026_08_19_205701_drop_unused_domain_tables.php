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
        Schema::dropIfExists('legacy_import_mappings');
        Schema::dropIfExists('report_item_assets');
        Schema::dropIfExists('hidden_parameters');
        Schema::dropIfExists('parameter_group_parameter');
        Schema::dropIfExists('parameter_groups');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data removed by this migration must be restored from its pre-deployment backup.
    }
};
