<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->pruneFormsMissingMetricLinks();
        $this->pruneDuplicateMetricForms();
        $this->guardMetricLinkConstraintsReady();

        Schema::table('forms', function (Blueprint $table): void {
            $table->dropForeign(['metric_id']);
            $table->dropIndex(['metric_id']);
        });

        Schema::table('forms', function (Blueprint $table): void {
            $table->ulid('metric_id')->nullable(false)->change();
        });

        Schema::table('forms', function (Blueprint $table): void {
            $table->unique('metric_id');
            $table->foreign('metric_id')->references('id')->on('metrics')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table): void {
            $table->dropForeign(['metric_id']);
            $table->dropUnique(['metric_id']);
        });

        Schema::table('forms', function (Blueprint $table): void {
            $table->ulid('metric_id')->nullable()->change();
        });

        Schema::table('forms', function (Blueprint $table): void {
            $table->index(['metric_id']);
            $table->foreign('metric_id')->references('id')->on('metrics')->nullOnDelete();
        });
    }

    private function pruneFormsMissingMetricLinks(): void
    {
        DB::table('forms')
            ->whereNull('metric_id')
            ->delete();
    }

    private function pruneDuplicateMetricForms(): void
    {
        $duplicateMetricIds = DB::table('forms')
            ->whereNotNull('metric_id')
            ->select('metric_id', DB::raw('COUNT(*) as form_count'))
            ->groupBy('metric_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('metric_id')
            ->all();

        foreach ($duplicateMetricIds as $metricId) {
            if (! is_string($metricId) || $metricId === '') {
                continue;
            }

            $formIds = DB::table('forms')
                ->where('metric_id', $metricId)
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->pluck('id')
                ->map(fn ($formId) => (string) $formId)
                ->all();

            $formIdsToDelete = array_slice($formIds, 1);

            if ($formIdsToDelete === []) {
                continue;
            }

            DB::table('forms')
                ->whereIn('id', $formIdsToDelete)
                ->delete();
        }
    }

    private function guardMetricLinkConstraintsReady(): void
    {
        $remainingNullCount = DB::table('forms')
            ->whereNull('metric_id')
            ->count();

        if ($remainingNullCount > 0) {
            throw new RuntimeException('Cannot enforce forms.metric_id NOT NULL after legacy cleanup.');
        }

        $duplicateCount = DB::table('forms')
            ->whereNotNull('metric_id')
            ->select('metric_id', DB::raw('COUNT(*) as form_count'))
            ->groupBy('metric_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicateCount > 0) {
            throw new RuntimeException('Cannot enforce unique forms.metric_id after legacy cleanup.');
        }
    }
};
