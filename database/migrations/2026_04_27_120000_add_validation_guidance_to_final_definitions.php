<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('final_definitions', function (Blueprint $table) {
            $table->text('validation_steps')
                ->nullable()
                ->after('implementation_gaps');
            $table->text('common_mismatch_causes')
                ->nullable()
                ->after('validation_steps');
        });

        Schema::table('org_final_definitions', function (Blueprint $table) {
            $table->text('validation_steps')
                ->nullable()
                ->after('implementation_gaps');
            $table->text('common_mismatch_causes')
                ->nullable()
                ->after('validation_steps');
        });
    }

    public function down(): void
    {
        Schema::table('final_definitions', function (Blueprint $table) {
            $table->dropColumn([
                'validation_steps',
                'common_mismatch_causes',
            ]);
        });

        Schema::table('org_final_definitions', function (Blueprint $table) {
            $table->dropColumn([
                'validation_steps',
                'common_mismatch_causes',
            ]);
        });
    }
};
