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
        Schema::dropIfExists('spreadsheet_import_queues');
        Schema::dropIfExists('spreadsheet_email_inbox_items');
        Schema::dropIfExists('spreadsheet_email_rules');

        Schema::create('spreadsheet_email_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained(indexName: 'se_rules_team_fk')->cascadeOnDelete();
            $table->foreignId('spreadsheet_template_id')->constrained('spreadsheet_templates', indexName: 'se_rules_template_fk')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->string('subject_operator', 20)->nullable();
            $table->string('subject_value')->nullable();
            $table->string('body_operator', 20)->nullable();
            $table->string('body_value')->nullable();
            $table->string('sender_operator', 20)->nullable();
            $table->string('sender_value')->nullable();
            $table->string('attachment_name_operator', 20)->nullable();
            $table->string('attachment_name_value')->nullable();
            $table->string('date_extraction_source', 30)->nullable()->default('auto');
            $table->string('date_extraction_pattern')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'se_rules_creator_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'se_rules_updater_fk')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'is_active'], 'se_rules_team_active_idx');
            $table->index(['team_id', 'priority'], 'se_rules_team_priority_idx');
        });

        Schema::create('spreadsheet_email_inbox_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained(indexName: 'se_inbox_team_fk')->cascadeOnDelete();
            $table->foreignId('spreadsheet_email_rule_id')->nullable()->constrained('spreadsheet_email_rules', indexName: 'se_inbox_rule_fk')->nullOnDelete();
            $table->foreignId('spreadsheet_template_id')->constrained('spreadsheet_templates', indexName: 'se_inbox_template_fk')->cascadeOnDelete();
            $table->string('email_message_id')->nullable();
            $table->string('sender_email');
            $table->string('sender_name')->nullable();
            $table->string('subject');
            $table->text('body_snippet')->nullable();
            $table->timestamp('email_received_at')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->date('extracted_reference_date')->nullable();
            $table->string('status', 30)->default('pending_confirmation');
            $table->foreignId('confirmed_by')->nullable()->constrained('users', indexName: 'se_inbox_confirmed_by_fk')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status'], 'se_inbox_team_status_idx');
            $table->index(['team_id', 'created_at'], 'se_inbox_team_created_idx');
        });

        Schema::create('spreadsheet_import_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained(indexName: 'siq_team_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users', indexName: 'siq_user_fk')->nullOnDelete();
            $table->foreignId('spreadsheet_template_id')->constrained('spreadsheet_templates', indexName: 'siq_template_fk')->cascadeOnDelete();
            $table->string('source_type', 30)->default('manual_upload');
            $table->foreignId('spreadsheet_email_inbox_item_id')->nullable()->constrained('spreadsheet_email_inbox_items', indexName: 'siq_inbox_item_fk')->nullOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->date('reference_date')->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->mediumText('error_trace')->nullable();
            $table->unsignedInteger('saved_values_count')->default(0);
            $table->foreignId('spreadsheet_import_batch_id')->nullable()->constrained('spreadsheet_import_batches', indexName: 'siq_batch_fk')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status'], 'siq_team_status_idx');
            $table->index(['status', 'created_at'], 'siq_status_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spreadsheet_import_queues');
        Schema::dropIfExists('spreadsheet_email_inbox_items');
        Schema::dropIfExists('spreadsheet_email_rules');
    }
};
