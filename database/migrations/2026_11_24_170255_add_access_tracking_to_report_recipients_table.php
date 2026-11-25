<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_recipients', function (Blueprint $table): void {
            $table->timestamp('first_accessed_at')->nullable()->after('revoked_at');
            $table->string('first_accessed_ip', 45)->nullable()->after('first_accessed_at');
            $table->string('last_accessed_ip', 45)->nullable()->after('last_accessed_at');
            $table->unsignedInteger('access_count')->default(0)->after('last_accessed_ip');
        });
    }

    public function down(): void
    {
        Schema::table('report_recipients', function (Blueprint $table): void {
            $table->dropColumn([
                'first_accessed_at',
                'first_accessed_ip',
                'last_accessed_ip',
                'access_count',
            ]);
        });
    }
};
