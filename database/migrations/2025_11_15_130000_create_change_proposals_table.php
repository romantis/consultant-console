<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('final_definitions', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('metric_id');
        });

        DB::table('final_definitions')->whereNull('version')->update(['version' => 1]);

        Schema::table('final_definitions', function (Blueprint $table) {
            $table->dropUnique('final_definitions_metric_id_unique');
            $table->unique(['metric_id', 'version'], 'final_definitions_metric_id_version_unique');
        });

        Schema::create('change_proposals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('engagement_id')->constrained('engagements')->cascadeOnDelete();
            $table->foreignUlid('metric_id')->constrained('metrics')->cascadeOnDelete();
            $table->foreignUlid('initiated_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->string('status', 20)->default('Open');
            $table->text('rationale');
            $table->jsonb('payload')->default('{}');
            $table->jsonb('approver_ids')->default('[]');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('merged_at')->nullable();
            $table->timestamps();

            $table->index(['metric_id', 'status'], 'change_proposals_metric_status_index');
            $table->index(['engagement_id', 'status'], 'change_proposals_engagement_status_index');
            $table->index(['organization_id', 'status'], 'change_proposals_org_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_proposals');

        Schema::table('final_definitions', function (Blueprint $table) {
            $table->dropUnique('final_definitions_metric_id_version_unique');
            $table->unique('metric_id');
            $table->dropColumn('version');
        });
    }
};
