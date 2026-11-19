<?php

use App\Enums\MetricStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metrics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('engagement_id')->nullable()->constrained('engagements')->nullOnDelete();
            $table->foreignUlid('metric_template_id')->nullable()->constrained('metric_templates')->nullOnDelete();
            $table->string('slug')->nullable();
            $table->string('name');
            $table->string('status', 30)->default(MetricStatus::AWAITING_FORMS->value);
            $table->timestamp('last_submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};
