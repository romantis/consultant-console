<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_participants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('engagement_id')->constrained('engagements')->cascadeOnDelete();
            $table->string('participant_type', 40);
            $table->foreignUlid('membership_id')->nullable()->constrained('memberships')->cascadeOnDelete();
            $table->foreignUlid('pending_membership_id')->nullable()->constrained('pending_memberships')->cascadeOnDelete();
            $table->string('membership_role', 20)->nullable();
            $table->string('stakeholder_role', 20)->nullable();
            $table->timestamps();

            $table->unique(['engagement_id', 'membership_id']);
            $table->unique(['engagement_id', 'pending_membership_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_participants');
    }
};
