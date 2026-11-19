<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('final_definitions', function (Blueprint $table) {
            $table->text('executive_takeaway')
                ->nullable()
                ->after('common_mismatch_causes');
        });

        Schema::table('org_final_definitions', function (Blueprint $table) {
            $table->text('executive_takeaway')
                ->nullable()
                ->after('common_mismatch_causes');
        });
    }

    public function down(): void
    {
        Schema::table('final_definitions', function (Blueprint $table) {
            $table->dropColumn('executive_takeaway');
        });

        Schema::table('org_final_definitions', function (Blueprint $table) {
            $table->dropColumn('executive_takeaway');
        });
    }
};
