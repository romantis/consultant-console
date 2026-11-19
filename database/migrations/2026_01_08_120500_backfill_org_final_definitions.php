<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('org_final_definitions')
            || ! Schema::hasTable('final_definitions')
            || ! Schema::hasTable('metrics')
            || ! Schema::hasTable('metric_templates')) {
            return;
        }

        $timestamp = now();
        $existingOrgMetricIds = DB::table('org_final_definitions')->pluck('org_metric_id')->all();
        $seenOrgMetrics = array_fill_keys($existingOrgMetricIds, true);
        $templateIdsByKey = DB::table('metric_templates')->pluck('id', 'key')->all();

        DB::table('final_definitions')
            ->join('metrics', 'final_definitions.metric_id', '=', 'metrics.id')
            ->whereNotNull('final_definitions.signed_off_at')
            ->whereNotNull('metrics.org_metric_id')
            ->orderByDesc('final_definitions.signed_off_at')
            ->orderByDesc('final_definitions.created_at')
            ->select([
                'final_definitions.id',
                'final_definitions.metric_id',
                'final_definitions.status',
                'final_definitions.definition_json',
                'final_definitions.notes',
                'final_definitions.signed_off_at',
                'final_definitions.signed_off_by',
                'final_definitions.created_at',
                'final_definitions.updated_at',
                'metrics.org_metric_id',
                'metrics.metric_template_id',
                'metrics.key as metric_key',
            ])
            ->lazy()
            ->each(function ($definition) use (&$seenOrgMetrics, $templateIdsByKey, $timestamp) {
                $orgMetricId = (string) $definition->org_metric_id;

                if ($orgMetricId === '' || isset($seenOrgMetrics[$orgMetricId])) {
                    return;
                }

                $templateId = $definition->metric_template_id
                    ?: ($templateIdsByKey[$definition->metric_key] ?? null);

                if (! $templateId) {
                    return;
                }

                DB::table('org_final_definitions')->insert([
                    'id' => (string) Str::ulid(),
                    'org_metric_id' => $orgMetricId,
                    'source_metric_id' => $definition->metric_id,
                    'source_metric_template_id' => $templateId,
                    'status' => $definition->status,
                    'version' => 1,
                    'definition_json' => $definition->definition_json ?? '{}',
                    'notes' => $definition->notes,
                    'owner_participant_id' => null,
                    'signed_off_at' => $definition->signed_off_at,
                    'signed_off_by' => $definition->signed_off_by,
                    'created_at' => $definition->created_at ?? $timestamp,
                    'updated_at' => $definition->updated_at ?? $timestamp,
                ]);

                $seenOrgMetrics[$orgMetricId] = true;
            });
    }

    public function down(): void {}
};
