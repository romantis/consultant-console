<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VectorHealth extends Command
{
    protected $signature = 'mh:vector:health
        {--install : Attempt to install the pgvector extension if missing}
        {--connection= : DB connection to use (defaults to database.default)}';

    protected $description = 'Check (and optionally install) pgvector and report vector-readiness.';

    public function handle(): int
    {
        $conn = $this->option('connection') ?: Config::get('database.default', 'pgsql');

        // Basic sanity: only meaningful for Postgres
        $driver = Config::get("database.connections.$conn.driver");
        if ($driver !== 'pgsql') {
            $this->warn("Connection [$conn] uses driver [$driver]; this command is only relevant for Postgres.");

            return self::INVALID;
        }

        $this->info("Using connection: $conn");

        // 1) Check whether extension exists
        $hasVector = $this->hasVectorExtension($conn);
        $this->line('pgvector extension: '.($hasVector ? '<info>present</info>' : '<comment>missing</comment>'));

        // 2) Optionally install it
        if (! $hasVector && $this->option('install')) {
            $installed = $this->attemptInstall($conn);
            $hasVector = $installed ? $this->hasVectorExtension($conn) : false;
            $this->line('pgvector extension after install attempt: '.($hasVector ? '<info>present</info>' : '<error>still missing</error>'));
        }

        // 3) Report FEATURE_VECTOR flag + config
        $flag = (bool) env('FEATURE_VECTOR', false);
        $enabledCfg = (bool) Config::get("database.connections.$conn.extensions.pgvector.enabled", false);
        $this->line('FEATURE_VECTOR (.env): '.($flag ? '<info>true</info>' : '<comment>false</comment>'));
        $this->line('config.extensions.pgvector.enabled: '.($enabledCfg ? '<info>true</info>' : '<comment>false</comment>'));

        // 4) Optional schema checks (safe if you don’t have these yet)
        $this->reportSchemaStatus($conn);

        // Exit code communicates readiness
        if ($flag && ! $hasVector) {
            $this->error('Vector feature is enabled but pgvector is not installed.');

            return self::FAILURE;
        }

        $this->info('Vector health check complete.');

        return self::SUCCESS;
    }

    private function hasVectorExtension(string $conn): bool
    {
        try {
            // pgvector extension is named "vector"
            $val = DB::connection($conn)->selectOne("select 1 as x from pg_extension where extname = 'vector'");

            return (bool) $val;
        } catch (Exception $e) {
            $this->error("Failed checking pg_extension: {$e->getMessage()}");

            return false;
        }
    }

    private function attemptInstall(string $conn): bool
    {
        $installSql = Config::get("database.connections.$conn.extensions.pgvector.install_sql");
        if (! $installSql || ! is_string($installSql)) {
            $this->warn('No install_sql path configured at database.connections.{connection}.extensions.pgvector.install_sql');

            return false;
        }
        if (! file_exists($installSql)) {
            $this->warn("Install SQL not found at: $installSql");

            return false;
        }

        $this->info("Running install SQL: $installSql");
        try {
            $sql = file_get_contents($installSql);
            DB::connection($conn)->unprepared($sql);
            $this->info('Install SQL executed.');

            return true;
        } catch (Exception $e) {
            $this->error("Failed running install SQL: {$e->getMessage()}");

            return false;
        }
    }

    private function reportSchemaStatus(string $conn): void
    {
        // Embeddings table is optional now; report if present
        try {
            $hasEmbeddings = Schema::connection($conn)->hasTable('embeddings');
            $this->line('embeddings table: '.($hasEmbeddings ? '<info>present</info>' : '<comment>not found</comment>'));

            if ($hasEmbeddings) {
                // Look for an ivfflat index (if you add it later)
                $indexes = DB::connection($conn)->select("
                    select indexname, indexdef
                    from pg_indexes
                    where schemaname = any (current_schemas(false))
                      and tablename = 'embeddings'
                      and indexdef ilike '%USING ivfflat%'
                ");
                if (count($indexes)) {
                    $this->line('ivfflat index: <info>present</info>');
                } else {
                    $this->line('ivfflat index: <comment>not found</comment>');
                }
            }
        } catch (Exception $e) {
            $this->warn("Schema check skipped ({$e->getMessage()})");
        }
    }
}
