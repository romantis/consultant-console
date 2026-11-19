<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metrics', function (Blueprint $table) {
            $table->string('recompute_status')
                ->default('idle')
                ->after('last_submitted_at');
            $table->timestamp('recompute_queued_at')
                ->nullable()
                ->after('recompute_status');
            $table->timestamp('recompute_finished_at')
                ->nullable()
                ->after('recompute_queued_at');
        });
    }

    public function down(): void
    {
        Schema::table('metrics', function (Blueprint $table) {
            $table->dropColumn([
                'recompute_status',
                'recompute_queued_at',
                'recompute_finished_at',
            ]);
        });
    }
};
