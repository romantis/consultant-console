<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_workshop_session_participants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('session_id')
                ->constrained('engagement_workshop_sessions')
                ->cascadeOnDelete();
            $table->foreignUlid('engagement_participant_id')
                ->constrained('engagement_participants')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['session_id', 'engagement_participant_id'],
                'ews_participants_unique'
            );
            $table->index('engagement_participant_id', 'ews_participant_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_workshop_session_participants');
    }
};
