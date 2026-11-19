<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('reconciliation_workshops', 'session_id')) {
            return;
        }

        Schema::table('reconciliation_workshops', function (Blueprint $table) {
            $table->foreignUlid('session_id')
                ->nullable()
                ->after('engagement_id')
                ->constrained('engagement_workshop_sessions')
                ->nullOnDelete();

            $table->index(['engagement_id', 'session_id'], 'rw_engagement_session_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('reconciliation_workshops', 'session_id')) {
            return;
        }

        Schema::table('reconciliation_workshops', function (Blueprint $table) {
            $table->dropIndex('rw_engagement_session_idx');
            $table->dropConstrainedForeignId('session_id');
        });
    }
};
