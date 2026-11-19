<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_workshops', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('metric_id')->constrained('metrics')->cascadeOnDelete();
            $table->foreignUlid('engagement_id')->constrained('engagements')->cascadeOnDelete();
            $table->foreignUlid('facilitator_id')->nullable()->constrained('memberships');
            $table->timestampTz('scheduled_at')->nullable();
            $table->json('participants')->nullable();
            $table->json('decision_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->string('transcript_url')->nullable();
            $table->timestamps();

            $table->index(['metric_id', 'scheduled_at']);
            $table->index(['engagement_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_workshops');
    }
};
