<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_final_definitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('org_metric_id')->constrained('org_metrics')->cascadeOnDelete();
            $table->foreignUlid('source_metric_id')->constrained('metrics')->cascadeOnDelete();
            $table->foreignUlid('source_metric_template_id')->constrained('metric_templates')->cascadeOnDelete();
            $table->string('status', 20)->default('PendingApproval');
            $table->unsignedInteger('version')->default(1);
            $table->jsonb('definition_json')->default('{}');
            $table->text('notes')->nullable();
            $table->foreignUlid('owner_participant_id')
                ->nullable()
                ->constrained('org_metric_participants')
                ->nullOnDelete();
            $table->timestampTz('signed_off_at')->nullable();
            $table->foreignUlid('signed_off_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['org_metric_id', 'version'], 'org_final_definitions_org_metric_version_unique');
            $table->index('status', 'org_final_definitions_status_index');
            $table->index('signed_off_at', 'org_final_definitions_signed_off_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_final_definitions');
    }
};
