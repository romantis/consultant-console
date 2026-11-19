<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_workshop_session_metrics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('session_id')
                ->constrained('engagement_workshop_sessions')
                ->cascadeOnDelete();
            $table->foreignUlid('metric_id')
                ->constrained('metrics')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['session_id', 'metric_id'], 'ews_metrics_unique');
            $table->index('metric_id', 'ews_metrics_metric_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_workshop_session_metrics');
    }
};
