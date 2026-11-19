<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('final_definitions', function (Blueprint $table) {
            $table->foreignUlid('owner_participant_id')
                ->nullable()
                ->after('notes')
                ->constrained('metric_participants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('final_definitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_participant_id');
        });
    }
};
