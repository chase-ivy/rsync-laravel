<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SECURITY (Supabase): every table in the "public" schema is reachable through Supabase's
 * Data API by anyone holding the project's public (anon) key, unless Row Level Security is on
 * and the anon/authenticated grants are removed. RSYNC never uses the Data API — Laravel connects
 * as "postgres", which bypasses RLS — so we switch RLS on everywhere and remove those grants.
 *
 * Safe on a plain PostgreSQL server too: the role-specific parts are skipped when the
 * Supabase roles "anon"/"authenticated" don't exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return; // e.g. the in-memory SQLite database used by tests
        }

        DB::unprepared(<<<'SQL'
DO $$
DECLARE
    t record;
    has_api_roles boolean := EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon')
                         AND EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated');
BEGIN
    FOR t IN SELECT tablename FROM pg_tables WHERE schemaname = 'public' LOOP
        EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY', t.tablename);
        IF has_api_roles THEN
            EXECUTE format('REVOKE ALL ON TABLE public.%I FROM anon, authenticated', t.tablename);
        END IF;
    END LOOP;

    IF has_api_roles THEN
        REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM anon, authenticated;
        -- Tables/sequences created later by this same role get no API grants either.
        EXECUTE format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA public REVOKE ALL ON TABLES FROM anon, authenticated', current_user);
        EXECUTE format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA public REVOKE ALL ON SEQUENCES FROM anon, authenticated', current_user);
    END IF;
END
$$;
SQL);
    }

    public function down(): void
    {
        // Intentionally empty: never re-open the tables to the public API.
    }
};