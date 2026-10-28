<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('calculation_rules')) {
            Schema::create('calculation_rules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('type', 40);
                foreach (['primary', 'secondary', 'destination'] as $role) {
                    $table->foreignId($role.'_parameter_id')->constrained('parameters')->restrictOnDelete();
                }
                foreach (['primary', 'secondary', 'result'] as $role) {
                    $table->double($role.'_multiplier')->nullable();
                }
                $table->timestamps();
                $table->softDeletes();
                $table->unsignedBigInteger('active_destination_parameter_id')->nullable()
                    ->storedAs('CASE WHEN deleted_at IS NULL THEN destination_parameter_id ELSE NULL END');
                $table->unique(['team_id', 'active_destination_parameter_id'], 'calculation_destination_unique');
                $table->index(['team_id', 'primary_parameter_id']);
                $table->index(['team_id', 'secondary_parameter_id']);
            });
        }

        if (! Schema::hasTable('calculation_runs')) {
            Schema::create('calculation_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->foreignId('calculation_rule_id')->constrained()->restrictOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->date('start_date');
                $table->date('end_date');
                $table->json('rule_ids');
                $table->string('status')->default('pending');
                $table->timestamp('cursor_at')->nullable();
                foreach (['processed', 'total', 'created', 'updated', 'removed'] as $counter) {
                    $table->unsignedBigInteger($counter.'_count')->default(0);
                }
                $table->text('error')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
                $table->index(['team_id', 'status']);
            });
        }

        if (! Schema::hasColumn('parameter_values', 'calculation_rule_id')) {
            Schema::table('parameter_values', function (Blueprint $table): void {
                $table->foreignId('calculation_rule_id')->nullable();
            });
        }
        $foreignKeys = Schema::getForeignKeys('parameter_values');
        if (collect($foreignKeys)->contains(fn (array $foreign): bool => $foreign['columns'] === ['calculation_rule_id'])) {
            return;
        }
        if (DB::getDriverName() !== 'mysql') {
            Schema::table('parameter_values', function (Blueprint $table): void {
                $table->foreign('calculation_rule_id')->references('id')->on('calculation_rules')->restrictOnDelete();
            });

            return;
        }
        $hasOrphans = DB::table('parameter_values as readings')
            ->leftJoin('calculation_rules as rules', 'rules.id', '=', 'readings.calculation_rule_id')
            ->whereNotNull('readings.calculation_rule_id')->whereNull('rules.id')->exists();
        if ($hasOrphans) {
            throw new RuntimeException('Existem leituras com referência a cálculos inexistentes.');
        }
        $originalChecks = DB::scalar('SELECT @@SESSION.foreign_key_checks');
        try {
            // INPLACE avoids copying millions of legacy readings, including their zero dates.
            // The new nullable references were checked above; enforcement resumes immediately.
            DB::statement('SET SESSION foreign_key_checks = 0');
            DB::statement('ALTER TABLE parameter_values ADD CONSTRAINT parameter_values_calculation_rule_id_foreign FOREIGN KEY (calculation_rule_id) REFERENCES calculation_rules (id) ON DELETE RESTRICT, ALGORITHM=INPLACE, LOCK=NONE');
        } finally {
            DB::statement('SET SESSION foreign_key_checks = ?', [(int) $originalChecks]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parameter_values', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('calculation_rule_id');
        });
        Schema::dropIfExists('calculation_runs');
        Schema::dropIfExists('calculation_rules');
    }
};
