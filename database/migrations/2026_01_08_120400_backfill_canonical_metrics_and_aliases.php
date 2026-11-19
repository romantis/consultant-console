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
        if (! Schema::hasTable('canonical_metrics') || ! Schema::hasTable('metric_templates') || ! Schema::hasTable('org_metrics')) {
            return;
        }

        $timestamp = now();
        $canonicalByKey = DB::table('canonical_metrics')->pluck('id', 'key')->all();

        $normalizeKey = function (?string $key, ?string $fallbackName) {
            $value = trim((string) $key);

            if ($value === '') {
                $value = Str::slug((string) $fallbackName);
            }

            if ($value === '') {
                $value = 'metric-'.Str::lower(Str::random(6));
            }

            if (strlen($value) > 120) {
                $value = Str::limit($value, 120, '');
            }

            return $value;
        };

        DB::table('metric_templates')
            ->select(['id', 'key', 'name', 'description', 'created_at', 'updated_at'])
            ->orderBy('created_at')
            ->lazy()
            ->each(function ($template) use (&$canonicalByKey, $normalizeKey, $timestamp) {
                $key = $normalizeKey($template->key, $template->name);

                if (! isset($canonicalByKey[$key])) {
                    $canonicalId = (string) Str::ulid();

                    DB::table('canonical_metrics')->insert([
                        'id' => $canonicalId,
                        'key' => $key,
                        'name' => $template->name ?: $key,
                        'description' => $template->description,
                        'is_active' => true,
                        'created_at' => $template->created_at ?? $timestamp,
                        'updated_at' => $template->updated_at ?? $timestamp,
                    ]);

                    $canonicalByKey[$key] = $canonicalId;
                }
            });

        if (Schema::hasTable('metrics')) {
            DB::table('metrics')
                ->select(['id', 'key', 'name', 'created_at', 'updated_at'])
                ->orderBy('created_at')
                ->lazy()
                ->each(function ($metric) use (&$canonicalByKey, $normalizeKey, $timestamp) {
                    $key = $normalizeKey($metric->key, $metric->name);

                    if (isset($canonicalByKey[$key])) {
                        return;
                    }

                    $canonicalId = (string) Str::ulid();

                    DB::table('canonical_metrics')->insert([
                        'id' => $canonicalId,
                        'key' => $key,
                        'name' => $metric->name ?: $key,
                        'description' => null,
                        'is_active' => true,
                        'created_at' => $metric->created_at ?? $timestamp,
                        'updated_at' => $metric->updated_at ?? $timestamp,
                    ]);

                    $canonicalByKey[$key] = $canonicalId;
                });
        }

        DB::table('metric_templates')
            ->select(['id', 'key', 'name', 'canonical_metric_id'])
            ->whereNull('canonical_metric_id')
            ->orderBy('created_at')
            ->lazy()
            ->each(function ($template) use (&$canonicalByKey, $normalizeKey, $timestamp) {
                $key = $normalizeKey($template->key, $template->name);

                if (! isset($canonicalByKey[$key])) {
                    $canonicalId = (string) Str::ulid();

                    DB::table('canonical_metrics')->insert([
                        'id' => $canonicalId,
                        'key' => $key,
                        'name' => $template->name ?: $key,
                        'description' => null,
                        'is_active' => true,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);

                    $canonicalByKey[$key] = $canonicalId;
                }

                DB::table('metric_templates')
                    ->where('id', $template->id)
                    ->update(['canonical_metric_id' => $canonicalByKey[$key]]);
            });

        DB::table('org_metrics')
            ->select(['id', 'key', 'name', 'alias', 'canonical_metric_id'])
            ->orderBy('created_at')
            ->lazy()
            ->each(function ($orgMetric) use (&$canonicalByKey, $normalizeKey, $timestamp) {
                $key = $normalizeKey($orgMetric->key, $orgMetric->name);

                if (! isset($canonicalByKey[$key])) {
                    $canonicalId = (string) Str::ulid();

                    DB::table('canonical_metrics')->insert([
                        'id' => $canonicalId,
                        'key' => $key,
                        'name' => $orgMetric->name ?: $key,
                        'description' => null,
                        'is_active' => true,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);

                    $canonicalByKey[$key] = $canonicalId;
                }

                $updates = [];

                if (! $orgMetric->canonical_metric_id) {
                    $updates['canonical_metric_id'] = $canonicalByKey[$key];
                }

                if ($orgMetric->alias === null) {
                    $updates['alias'] = $orgMetric->name;
                }

                if ($updates !== []) {
                    DB::table('org_metrics')
                        ->where('id', $orgMetric->id)
                        ->update($updates);
                }
            });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE metric_templates ALTER COLUMN canonical_metric_id SET NOT NULL');
            DB::statement('ALTER TABLE org_metrics ALTER COLUMN canonical_metric_id SET NOT NULL');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE metric_templates MODIFY canonical_metric_id CHAR(26) NOT NULL');
            DB::statement('ALTER TABLE org_metrics MODIFY canonical_metric_id CHAR(26) NOT NULL');
        }

        Schema::table('org_metrics', function (Blueprint $table) {
            $table->unique(['organization_id', 'canonical_metric_id'], 'org_metrics_org_canonical_unique');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('org_metrics')) {
            Schema::table('org_metrics', function (Blueprint $table) {
                $table->dropUnique('org_metrics_org_canonical_unique');
            });
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE metric_templates ALTER COLUMN canonical_metric_id DROP NOT NULL');
            DB::statement('ALTER TABLE org_metrics ALTER COLUMN canonical_metric_id DROP NOT NULL');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE metric_templates MODIFY canonical_metric_id CHAR(26) NULL');
            DB::statement('ALTER TABLE org_metrics MODIFY canonical_metric_id CHAR(26) NULL');
        }
    }
};
