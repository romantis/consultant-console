<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_schedules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('engagement_id')->constrained('engagements')->cascadeOnDelete();
            $table->foreignUlid('metric_id')->constrained('metrics')->cascadeOnDelete();
            $table->foreignUlid('form_assignment_id')->constrained('form_assignment')->cascadeOnDelete();
            $table->string('window', 20);
            $table->string('channel', 20)->default('email');
            $table->timestamp('deliver_at');
            $table->timestamp('delivered_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['form_assignment_id', 'window', 'channel']);
            $table->index(['status', 'deliver_at']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_schedules');
    }
};
