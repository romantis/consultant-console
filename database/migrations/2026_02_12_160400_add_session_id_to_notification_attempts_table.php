<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('notification_attempts', 'session_id')) {
            return;
        }

        Schema::table('notification_attempts', function (Blueprint $table) {
            $table->foreignUlid('session_id')
                ->nullable()
                ->after('engagement_id')
                ->constrained('engagement_workshop_sessions')
                ->nullOnDelete();

            $table->index(['session_id', 'attempted_at'], 'na_session_attempted_at_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('notification_attempts', 'session_id')) {
            return;
        }

        Schema::table('notification_attempts', function (Blueprint $table) {
            $table->dropIndex('na_session_attempted_at_idx');
            $table->dropConstrainedForeignId('session_id');
        });
    }
};
