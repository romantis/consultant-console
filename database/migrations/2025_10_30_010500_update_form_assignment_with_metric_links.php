<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_assignment', function (Blueprint $table) {
            $table->foreignUlid('metric_id')->nullable()->after('form_id')->constrained('metrics')->nullOnDelete();
            $table->foreignUlid('metric_participant_id')->nullable()->after('metric_id')->constrained('metric_participants')->nullOnDelete();
            $table->string('responsibility', 20)->nullable()->after('metric_participant_id');
            $table->jsonb('role_tags_json')->nullable()->after('responsibility');

            $table->index(['metric_id']);
            $table->index(['metric_participant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('form_assignment', function (Blueprint $table) {
            $table->dropConstrainedForeignId('metric_participant_id');
            $table->dropConstrainedForeignId('metric_id');
            $table->dropColumn('responsibility');
            $table->dropColumn('role_tags_json');
        });
    }
};
