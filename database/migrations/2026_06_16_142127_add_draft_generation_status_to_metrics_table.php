<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metrics', function (Blueprint $table) {
            $table->string('draft_generation_status')
                ->default('idle')
                ->after('recompute_finished_at');
            $table->text('draft_generation_error')
                ->nullable()
                ->after('draft_generation_status');
        });
    }

    public function down(): void
    {
        Schema::table('metrics', function (Blueprint $table) {
            $table->dropColumn([
                'draft_generation_status',
                'draft_generation_error',
            ]);
        });
    }
};
