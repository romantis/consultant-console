<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canonical_metrics', function (Blueprint $table) {
            $table->foreignUlid('default_metric_template_id')
                ->nullable()
                ->after('description')
                ->constrained('metric_templates');
        });

        Schema::table('org_metrics', function (Blueprint $table) {
            $table->foreignUlid('default_metric_template_id')
                ->nullable()
                ->after('canonical_metric_id')
                ->constrained('metric_templates');
        });
    }

    public function down(): void
    {
        Schema::table('org_metrics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_metric_template_id');
        });

        Schema::table('canonical_metrics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_metric_template_id');
        });
    }
};
