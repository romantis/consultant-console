<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metric_participants', function (Blueprint $table) {
            $table->dropUnique('metric_participants_metric_id_user_id_responsibility_unique');
            $table->dropForeign(['user_id']);
        });

        Schema::table('metric_participants', function (Blueprint $table) {
            $table->ulid('user_id')->nullable()->change();
        });

        Schema::table('metric_participants', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignUlid('engagement_participant_id')
                ->nullable()
                ->after('metric_id')
                ->constrained('engagement_participants')
                ->cascadeOnDelete();
            $table->unique(
                ['metric_id', 'engagement_participant_id', 'responsibility'],
                'metric_participants_metric_engagement_responsibility_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('metric_participants', function (Blueprint $table) {
            $table->dropUnique('metric_participants_metric_engagement_responsibility_unique');
            $table->dropConstrainedForeignId('engagement_participant_id');
            $table->dropForeign(['user_id']);
        });

        Schema::table('metric_participants', function (Blueprint $table) {
            $table->ulid('user_id')->nullable(false)->change();
        });

        Schema::table('metric_participants', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['metric_id', 'user_id', 'responsibility']);
        });
    }
};
