<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_attempts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('engagement_id')->constrained('engagements')->cascadeOnDelete();
            $table->foreignUlid('metric_id')->constrained('metrics')->cascadeOnDelete();
            $table->foreignUlid('form_assignment_id')->constrained('form_assignment')->cascadeOnDelete();
            $table->foreignUlid('notification_id')->constrained('reminder_schedules')->cascadeOnDelete();
            $table->string('channel', 20)->default('email');
            $table->jsonb('provider_response')->nullable();
            $table->string('status', 20)->default('sent');
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index(['notification_id', 'attempted_at']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_attempts');
    }
};
