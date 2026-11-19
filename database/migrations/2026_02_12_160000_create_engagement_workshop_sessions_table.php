<?php

use App\Enums\EngagementWorkshopSessionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_workshop_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignUlid('engagement_id')
                ->constrained('engagements')
                ->cascadeOnDelete();
            $table->foreignUlid('facilitator_membership_id')
                ->nullable()
                ->constrained('memberships')
                ->nullOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('timezone', 120);
            $table->string('status', 24)->default(EngagementWorkshopSessionStatus::SCHEDULED->value);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'engagement_id'], 'ews_org_engagement_idx');
            $table->index(['engagement_id', 'status', 'starts_at'], 'ews_engagement_status_start_idx');
            $table->index(['facilitator_membership_id', 'starts_at'], 'ews_facilitator_start_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_workshop_sessions');
    }
};
