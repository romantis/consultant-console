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
            $table->ulid('org_metric_id')->nullable()->after('metric_template_id');
        });

        Schema::table('metric_templates', function (Blueprint $table) {
            $table->ulid('org_metric_id')->nullable()->after('id');
        });

        if (! Schema::hasTable('org_metrics')) {
            return;
        }

        $templateLookup = DB::table('metric_templates')
            ->select(['id', 'key', 'name'])
            ->get()
            ->keyBy('id');

        $orgMetricIds = [];
        $timestamp = now();

        DB::table('metrics')
            ->select(['id', 'organization_id', 'metric_template_id', 'key', 'name'])
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

                    if ($template?->id) {
                        DB::table('metric_templates')
                            ->where('id', $template->id)
                            ->whereNull('org_metric_id')
                            ->update(['org_metric_id' => $orgMetricId]);
                    }
                }

                DB::table('metrics')
                    ->where('id', $metric->id)
                    ->update(['org_metric_id' => $orgMetricIds[$bucket]]);
            });
    }

    public function down(): void
    {
        Schema::table('metrics', function (Blueprint $table) {
            $table->dropColumn('org_metric_id');
        });

        Schema::table('metric_templates', function (Blueprint $table) {
            $table->dropColumn('org_metric_id');
        });
    }
};
