<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metrics', function (Blueprint $table) {
            $table->timestamp('definitions_changed_at')
                ->nullable()
                ->after('recompute_finished_at');
        });

        DB::table('metrics')->whereNull('definitions_changed_at')->update([
            'definitions_changed_at' => DB::raw('COALESCE(last_submitted_at, updated_at, CURRENT_TIMESTAMP)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('metrics', function (Blueprint $table) {
            $table->dropColumn('definitions_changed_at');
        });
    }
};
