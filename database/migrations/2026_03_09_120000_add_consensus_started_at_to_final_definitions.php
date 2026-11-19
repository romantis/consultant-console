<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('final_definitions', function (Blueprint $table) {
            $table->timestampTz('consensus_started_at')
                ->nullable()
                ->after('owner_participant_id');
        });
    }

    public function down(): void
    {
        Schema::table('final_definitions', function (Blueprint $table) {
            $table->dropColumn('consensus_started_at');
        });
    }
};
