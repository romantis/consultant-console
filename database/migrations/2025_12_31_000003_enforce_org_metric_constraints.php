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
        if (Schema::hasTable('org_metrics')) {
            $templateLookup = DB::table('metric_templates')
                ->select(['id', 'key', 'name'])
                ->get()
                ->keyBy('id');

            $existingOrgMetrics = DB::table('org_metrics')
                ->select(['id', 'organization_id', 'key'])
                ->get()
                ->mapWithKeys(fn ($row) => [$row->organization_id.'|'.$row->key => $row->id])
                ->all();

            $orgMetricIds = $existingOrgMetrics;
            $timestamp = now();

            DB::table('metrics')
                ->select(['id', 'organization_id', 'metric_template_id', 'key', 'name', 'org_metric_id'])
                ->whereNull('org_metric_id')
                ->orderBy('created_at')
                ->lazy()
                ->each(function ($metric) use ($templateLookup, &$orgMetricIds, $timestamp) {
                    $template = $metric->metric_template_id
                        ? $templateLookup->get($metric->metric_template_id)
                        : null;

                    $groupKey = $template?->key ?? $metric->key ?? $metric->name;
                    $groupKey = (string) ($groupKey ?? '');

                    if ($groupKey === '') {
                        $groupKey = 'metric-'.Str::lower(Str::random(6));
                    }

                    if (strlen($groupKey) > 120) {
                        $groupKey = Str::limit($groupKey, 120, '');
                    }

                    $orgId = (string) $metric->organization_id;
                    $bucket = $orgId.'|'.$groupKey;

                    if (! isset($orgMetricIds[$bucket])) {
                        $orgMetricId = (string) Str::ulid();
                        $orgMetricName = $template?->name ?? $metric->name ?? $groupKey;

                        DB::table('org_metrics')->insert([
                            'id' => $orgMetricId,
                            'organization_id' => $metric->organization_id,
                            'key' => $groupKey,
                            'slug' => null,
                            'name' => $orgMetricName,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ]);

                        $orgMetricIds[$bucket] = $orgMetricId;
                    }

                    DB::table('metrics')
                        ->where('id', $metric->id)
                        ->update(['org_metric_id' => $orgMetricIds[$bucket]]);

                    if ($template?->id) {
                        DB::table('metric_templates')
                            ->where('id', $template->id)
                            ->whereNull('org_metric_id')
                            ->update(['org_metric_id' => $orgMetricIds[$bucket]]);
                    }
                });
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE metrics ALTER COLUMN org_metric_id SET NOT NULL');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE metrics MODIFY org_metric_id CHAR(26) NOT NULL');
        }

        Schema::table('metrics', function (Blueprint $table) {
            $table->index('org_metric_id', 'metrics_org_metric_id_index');
            $table->foreign('org_metric_id', 'metrics_org_metric_id_foreign')
                ->references('id')
                ->on('org_metrics')
                ->cascadeOnDelete();
        });

        Schema::table('metric_templates', function (Blueprint $table) {
            $table->index('org_metric_id', 'metric_templates_org_metric_id_index');
            $table->foreign('org_metric_id', 'metric_templates_org_metric_id_foreign')
                ->references('id')
                ->on('org_metrics')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('metric_templates', function (Blueprint $table) {
            $table->dropForeign('metric_templates_org_metric_id_foreign');
            $table->dropIndex('metric_templates_org_metric_id_index');
        });

        Schema::table('metrics', function (Blueprint $table) {
            $table->dropForeign('metrics_org_metric_id_foreign');
            $table->dropIndex('metrics_org_metric_id_index');
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE metrics ALTER COLUMN org_metric_id DROP NOT NULL');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE metrics MODIFY org_metric_id CHAR(26) NULL');
        }
    }
};
