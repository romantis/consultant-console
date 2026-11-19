<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_definitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('metric_id')->constrained('metrics')->cascadeOnDelete();
            $table->foreignUlid('form_submission_id')->nullable()->constrained('form_submissions')->nullOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type', 40)->default('form_submission');
            $table->string('status', 20)->default('Submitted');
            $table->jsonb('definition_json');
            $table->jsonb('normalized_json');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['metric_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_definitions');
    }
};
