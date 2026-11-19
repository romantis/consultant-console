<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_final_definitions', function (Blueprint $table) {
            $table->string('operational_status', 32)
                ->nullable()
                ->after('notes');
            $table->text('operational_status_notes')
                ->nullable()
                ->after('operational_status');
            $table->text('alignment_summary')
                ->nullable()
                ->after('operational_status_notes');
            $table->jsonb('implementation_gaps')
                ->nullable()
                ->after('alignment_summary');
        });
    }

    public function down(): void
    {
        Schema::table('org_final_definitions', function (Blueprint $table) {
            $table->dropColumn([
                'operational_status',
                'operational_status_notes',
                'alignment_summary',
                'implementation_gaps',
            ]);
        });
    }
};
