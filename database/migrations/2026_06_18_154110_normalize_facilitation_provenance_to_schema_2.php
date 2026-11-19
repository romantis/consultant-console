<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time normalization of persisted facilitation documents to schema 2.0.
 *
 * Schema 2.0 renames the `ai_suggested` provenance to `system_suggested` (AI
 * draft generation was removed; the deterministic projection is the only
 * producer). This rewrites stored documents and the schema_version column so the
 * persisted contract matches the published schema — it is not a read-time shim.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rewriteProvenance('ai_suggested', 'system_suggested', '2.0');
    }

    public function down(): void
    {
        $this->rewriteProvenance('system_suggested', 'ai_suggested', '1.0');
    }

    private function rewriteProvenance(string $from, string $to, string $schemaVersion): void
    {
        DB::table('metric_facilitation_briefs')
            ->select('id', 'document')
            ->orderBy('id')
            ->each(function (object $brief) use ($from, $to, $schemaVersion): void {
                $document = json_decode((string) $brief->document, true);

                if (! is_array($document)) {
                    return;
                }

                array_walk_recursive($document, function (mixed &$value, int|string $key) use ($from, $to): void {
                    if ($key === 'provenance' && $value === $from) {
                        $value = $to;
                    }
                });

                $document['schema_version'] = $schemaVersion;

                DB::table('metric_facilitation_briefs')
                    ->where('id', $brief->id)
                    ->update([
                        'document' => json_encode($document),
                        'schema_version' => $schemaVersion,
                    ]);
            });
    }
};
