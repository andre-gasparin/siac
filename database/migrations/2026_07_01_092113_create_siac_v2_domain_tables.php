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
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('name')->index();
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('role');
            $table->index(['user_id', 'is_default']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('current_team_id')->index();
        });

        Schema::create('monitored_systems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'team_id'], 'systems_id_team_unique');
            $table->index(['team_id', 'sort_order']);
            $table->index(['team_id', 'is_active']);
        });

        Schema::create('parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitored_system_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('tag')->nullable();
            $table->string('unit', 40)->nullable();
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->double('alert_1_min')->nullable();
            $table->double('alert_1_max')->nullable();
            $table->double('alert_2_min')->nullable();
            $table->double('alert_2_max')->nullable();
            $table->double('alert_3_min')->nullable();
            $table->double('alert_3_max')->nullable();
            $table->double('alert_4_min')->nullable();
            $table->double('alert_4_max')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign(['monitored_system_id', 'team_id'], 'parameters_system_team_fk')
                ->references(['id', 'team_id'])
                ->on('monitored_systems')
                ->restrictOnDelete();

            $table->unique(['id', 'team_id'], 'parameters_id_team_unique');
            $table->unique(['id', 'team_id', 'monitored_system_id'], 'parameters_scope_unique');
            $table->unique(['team_id', 'monitored_system_id', 'code'], 'parameters_system_code_unique');
            $table->index(['team_id', 'monitored_system_id', 'sort_order'], 'parameters_system_order_idx');
            $table->index(['team_id', 'is_active']);
        });

        Schema::create('parameter_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'name']);
            $table->index(['team_id', 'sort_order']);
        });

        Schema::create('parameter_group_parameter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parameter_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parameter_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['parameter_group_id', 'parameter_id'], 'group_parameter_unique');
            $table->index(['parameter_id', 'sort_order']);
        });

        Schema::create('hidden_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parameter_id');
            $table->timestamps();

            $table->foreign(['parameter_id', 'team_id'], 'hidden_parameters_parameter_team_fk')
                ->references(['id', 'team_id'])
                ->on('parameters')
                ->cascadeOnDelete();

            $table->unique(['team_id', 'user_id', 'parameter_id'], 'hidden_parameters_unique');
        });

        Schema::create('upload_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('selected_date')->nullable();
            $table->string('file_disk')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('config_name')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('warning_rows')->default(0);
            $table->text('error_summary')->nullable();
            $table->text('alert_summary')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'created_at']);
            $table->index(['team_id', 'selected_date']);
        });

        Schema::create('upload_import_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number')->nullable();
            $table->foreignId('parameter_id')->nullable();
            $table->timestamp('measured_at')->nullable();
            $table->string('severity');
            $table->string('code');
            $table->text('message');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->foreign(['parameter_id', 'team_id'], 'upload_issues_parameter_team_fk')
                ->references(['id', 'team_id'])
                ->on('parameters')
                ->restrictOnDelete();

            $table->index(['upload_batch_id', 'severity']);
            $table->index(['team_id', 'code']);
        });

        Schema::create('parameter_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitored_system_id');
            $table->foreignId('parameter_id');
            $table->timestamp('measured_at');
            $table->date('measured_date');
            $table->double('value')->nullable();
            $table->string('source_type')->default('manual');
            $table->foreignId('upload_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['parameter_id', 'team_id', 'monitored_system_id'], 'parameter_values_parameter_scope_fk')
                ->references(['id', 'team_id', 'monitored_system_id'])
                ->on('parameters')
                ->restrictOnDelete();

            $table->unique(['parameter_id', 'measured_at'], 'pv_parameter_measured_unique');
            $table->index(['team_id', 'parameter_id', 'measured_at'], 'pv_team_param_time_idx');
            $table->index(['team_id', 'monitored_system_id', 'measured_at'], 'pv_team_system_time_idx');
            $table->index(['team_id', 'measured_date'], 'pv_team_date_idx');
            $table->index('upload_batch_id', 'pv_upload_batch_idx');
        });

        Schema::create('parameter_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitored_system_id');
            $table->foreignId('parameter_id');
            $table->date('measured_date');
            $table->unsignedInteger('values_count')->default(0);
            $table->double('average_value')->nullable();
            $table->double('minimum_value')->nullable();
            $table->double('maximum_value')->nullable();
            $table->unsignedInteger('out_of_limit_count')->default(0);
            $table->timestamps();

            $table->foreign(['parameter_id', 'team_id', 'monitored_system_id'], 'daily_metrics_parameter_scope_fk')
                ->references(['id', 'team_id', 'monitored_system_id'])
                ->on('parameters')
                ->cascadeOnDelete();

            $table->unique(['team_id', 'monitored_system_id', 'parameter_id', 'measured_date'], 'pdm_scope_date_unique');
            $table->index(['team_id', 'measured_date'], 'pdm_team_date_idx');
            $table->index(['team_id', 'monitored_system_id', 'measured_date'], 'pdm_team_system_date_idx');
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finished_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->date('date_reference')->nullable();
            $table->string('status')->default('draft')->index();
            $table->text('comment')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->unsignedInteger('email_count')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'team_id'], 'reports_id_team_unique');
            $table->index(['team_id', 'date_reference']);
        });

        Schema::create('report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id');
            $table->foreignId('monitored_system_id')->nullable();
            $table->foreignId('parameter_id')->nullable();
            $table->foreignId('parent_report_item_id')->nullable()->constrained('report_items')->nullOnDelete();
            $table->timestamp('date_reference')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('show_data_results')->default(true);
            $table->boolean('hide_data')->default(false);
            $table->boolean('is_stopped')->default(false);
            $table->longText('comment')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign(['report_id', 'team_id'], 'report_items_report_team_fk')
                ->references(['id', 'team_id'])
                ->on('reports')
                ->cascadeOnDelete();

            $table->foreign(['parameter_id', 'team_id', 'monitored_system_id'], 'report_items_parameter_scope_fk')
                ->references(['id', 'team_id', 'monitored_system_id'])
                ->on('parameters')
                ->restrictOnDelete();

            $table->index(['team_id', 'report_id', 'sort_order'], 'report_items_order_idx');
            $table->index(['team_id', 'date_reference']);
        });

        Schema::create('report_item_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_item_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['report_item_id', 'kind']);
        });

        Schema::create('chart_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->boolean('is_favorite')->default(false);
            $table->json('options')->nullable();
            $table->json('markers')->nullable();
            $table->double('y1_min')->nullable();
            $table->double('y1_max')->nullable();
            $table->double('y2_min')->nullable();
            $table->double('y2_max')->nullable();
            $table->timestamps();

            $table->unique(['id', 'team_id'], 'chart_templates_id_team_unique');
            $table->index(['team_id', 'is_favorite']);
        });

        Schema::create('chart_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chart_template_id');
            $table->foreignId('monitored_system_id');
            $table->foreignId('parameter_id');
            $table->unsignedTinyInteger('axis')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('label')->nullable();
            $table->string('color', 20)->nullable();
            $table->json('options')->nullable();
            $table->timestamps();

            $table->foreign(['chart_template_id', 'team_id'], 'chart_series_template_team_fk')
                ->references(['id', 'team_id'])
                ->on('chart_templates')
                ->cascadeOnDelete();

            $table->foreign(['parameter_id', 'team_id', 'monitored_system_id'], 'chart_series_parameter_scope_fk')
                ->references(['id', 'team_id', 'monitored_system_id'])
                ->on('parameters')
                ->cascadeOnDelete();

            $table->index(['chart_template_id', 'sort_order'], 'chart_series_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_series');
        Schema::dropIfExists('chart_templates');
        Schema::dropIfExists('report_item_assets');
        Schema::dropIfExists('report_items');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('parameter_daily_metrics');
        Schema::dropIfExists('parameter_values');
        Schema::dropIfExists('upload_import_issues');
        Schema::dropIfExists('upload_batches');
        Schema::dropIfExists('hidden_parameters');
        Schema::dropIfExists('parameter_group_parameter');
        Schema::dropIfExists('parameter_groups');
        Schema::dropIfExists('parameters');
        Schema::dropIfExists('monitored_systems');
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_default']);
            $table->dropColumn('is_default');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn('is_active');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
