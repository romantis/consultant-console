<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('metrics', 'engagement_id')) {
            $this->refreshForeignKey('metrics', 'engagement_id');
        }

        if (Schema::hasColumn('forms', 'engagement_id')) {
            $this->refreshForeignKey('forms', 'engagement_id');
        }

        Schema::table('discrepancies', function (Blueprint $table) {
            if (! Schema::hasColumn('discrepancies', 'engagement_id')) {
                $table->foreignUlid('engagement_id')->after('metric_id')->constrained('engagements')->cascadeOnDelete();
            }
            $table->index(['engagement_id', 'status']);
        });

        if (Schema::hasColumn('discrepancies', 'engagement_id')) {
            DB::table('discrepancies')->select('id', 'metric_id')->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $engagementId = DB::table('metrics')->where('id', $row->metric_id)->value('engagement_id');
                    if ($engagementId) {
                        DB::table('discrepancies')->where('id', $row->id)->update(['engagement_id' => $engagementId]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('discrepancies', function (Blueprint $table) {
            if (Schema::hasColumn('discrepancies', 'engagement_id')) {
                $table->dropIndex('discrepancies_engagement_id_status_index');
                $table->dropConstrainedForeignId('engagement_id');
            }
        });
    }

    private function refreshForeignKey(string $tableName, string $column): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite cannot alter constraints inline; rely on validation layer.
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($column) {
            $table->dropForeign([$column]);
        });

        Schema::table($tableName, function (Blueprint $table) use ($column) {
            $table->foreign($column)->references('id')->on('engagements')->cascadeOnDelete();
        });
    }
};
