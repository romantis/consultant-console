<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_metrics', function (Blueprint $table) {
            $table->foreignUlid('canonical_metric_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('canonical_metrics');
            $table->string('alias')->nullable()->after('name');
        });

        Schema::table('metric_templates', function (Blueprint $table) {
            $table->foreignUlid('canonical_metric_id')
                ->nullable()
                ->after('org_metric_id')
                ->constrained('canonical_metrics');
        });
    }

    public function down(): void
    {
        Schema::table('metric_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('canonical_metric_id');
        });

        Schema::table('org_metrics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('canonical_metric_id');
            $table->dropColumn('alias');
        });
    }
};
