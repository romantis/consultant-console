<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metric_participants', function (Blueprint $table) {
            $table->string('subject_type')->nullable()->after('engagement_participant_id');
            $table->ulid('subject_id')->nullable()->after('subject_type');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('metric_participants', function (Blueprint $table) {
            $table->dropIndex('metric_participants_subject_type_subject_id_index');
            $table->dropColumn(['subject_type', 'subject_id']);
        });
    }
};
