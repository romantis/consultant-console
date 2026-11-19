<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engagement_participants', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('stakeholder_role');
            $table->index(['engagement_id', 'active']);
        });

        DB::table('engagement_participants')->update(['active' => true]);
    }

    public function down(): void
    {
        Schema::table('engagement_participants', function (Blueprint $table) {
            $table->dropIndex('engagement_participants_engagement_id_active_index');
            $table->dropColumn('active');
        });
    }
};
