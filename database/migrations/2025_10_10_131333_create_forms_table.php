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
        Schema::create('forms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('engagement_id')->nullable()->constrained('engagements')->nullOnDelete();
            $table->string('template_key'); // from metric_templates.key
            $table->string('title');
            $table->jsonb('schema_json'); // cloned from template.form_schema_json
            $table->string('status', 20)->default('Open'); // Open|Closed
            $table->timestamps();

            $table->index(['organization_id', 'engagement_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
