-- Optional pgvector extension bootstrap for the Consultant Console.
-- Run with: psql "$DATABASE_URL" -f database/migrations/vector_extensions.sql
-- Execute this script before enabling FEATURE_VECTOR in your environment.
-- It is safe to run multiple times; it exits gracefully if pgvector is unavailable.

DO $pgvector$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_available_extensions
        WHERE name = 'vector'
    ) THEN
        RAISE NOTICE 'pgvector extension is not available on this server. Skipping install.';
        RETURN;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_extension
        WHERE extname = 'vector'
    ) THEN
        EXECUTE 'CREATE EXTENSION vector';
        RAISE NOTICE 'pgvector extension installed.';
    ELSE
        RAISE NOTICE 'pgvector extension already present. Updating to latest.';
    END IF;

    EXECUTE 'ALTER EXTENSION vector UPDATE';
    RAISE NOTICE 'pgvector extension updated.';
END
$pgvector$;
