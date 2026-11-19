<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('final_definitions')
            && Schema::hasColumn('final_definitions', 'implementation_gap')) {
            Schema::table('final_definitions', function (Blueprint $table) {
                $table->dropColumn('implementation_gap');
            });
        }

        if (Schema::hasTable('org_final_definitions')
            && Schema::hasColumn('org_final_definitions', 'implementation_gap')) {
            Schema::table('org_final_definitions', function (Blueprint $table) {
                $table->dropColumn('implementation_gap');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('final_definitions')
            && ! Schema::hasColumn('final_definitions', 'implementation_gap')) {
            Schema::table('final_definitions', function (Blueprint $table) {
                $table->string('implementation_gap', 16)
                    ->nullable()
                    ->after('notes');
            });
        }

        if (Schema::hasTable('org_final_definitions')
            && ! Schema::hasColumn('org_final_definitions', 'implementation_gap')) {
            Schema::table('org_final_definitions', function (Blueprint $table) {
                $table->string('implementation_gap', 16)->nullable();
            });
        }
    }
};
