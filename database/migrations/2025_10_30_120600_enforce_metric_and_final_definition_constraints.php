<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metrics', function (Blueprint $table) {
            $table->string('key', 120)->nullable()->after('metric_template_id');
        });

        $templateKeys = DB::table('metric_templates')->pluck('key', 'id')->all();
        $assignedKeys = [];

        DB::table('metrics')
            ->select('id', 'engagement_id', 'metric_template_id', 'slug', 'name')
            ->orderBy('created_at')
            ->lazy()
            ->each(function ($metric) use (&$assignedKeys, $templateKeys) {
                $base = $templateKeys[$metric->metric_template_id] ?? null;

                if (! $base) {
                    $base = $metric->slug ?: Str::slug((string) $metric->name);
                }

                if (! $base) {
                    $base = 'metric-'.Str::lower(Str::random(6));
                }

                $scope = (string) ($metric->engagement_id ?? 'global');
                $candidate = $base;
                $suffix = 1;

                while (isset($assignedKeys["{$scope}|{$candidate}"])) {
                    $candidate = sprintf('%s-%s', $base, ++$suffix);
                }

                $assignedKeys["{$scope}|{$candidate}"] = true;

                DB::table('metrics')
                    ->where('id', $metric->id)
                    ->update(['key' => $candidate]);
            });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE metrics ALTER COLUMN key SET NOT NULL');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE metrics MODIFY key VARCHAR(120) NOT NULL');
        }

        Schema::table('metrics', function (Blueprint $table) {
            $table->unique(['engagement_id', 'key'], 'metrics_engagement_key_unique');
            $table->index(['organization_id', 'key'], 'metrics_org_key_index');
            $table->index(['engagement_id', 'status'], 'metrics_engagement_status_index');
        });

        Schema::table('final_definitions', function (Blueprint $table) {
            $table->index('status', 'final_definitions_status_index');
            $table->index('signed_off_at', 'final_definitions_signed_off_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('final_definitions', function (Blueprint $table) {
            $table->dropIndex('final_definitions_status_index');
            $table->dropIndex('final_definitions_signed_off_at_index');
        });

        Schema::table('metrics', function (Blueprint $table) {
            $table->dropUnique('metrics_engagement_key_unique');
            $table->dropIndex('metrics_org_key_index');
            $table->dropIndex('metrics_engagement_status_index');
            $table->dropColumn('key');
        });
    }
};
