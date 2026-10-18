<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Part H2 — tables Laravel needs on Render: logins (sessions) and the cache (rate limits,
 * "email sent less than 10 minutes ago" checks). Render's disk is wiped on every deploy,
 * so these can't stay in files there. Only NEW tables; nothing existing is touched.
 * Locked away from Supabase's public Data API like every other table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->bigInteger('expiration')->index();
            });
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->bigInteger('expiration')->index();
            });
        }

        // Same protection as 2026_10_16_100010: no access through Supabase's public API.
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
DO $$
DECLARE
    t text;
    has_api_roles boolean := EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon')
                         AND EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated');
BEGIN
    FOREACH t IN ARRAY ARRAY['sessions', 'cache', 'cache_locks'] LOOP
        EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY', t);
        IF has_api_roles THEN
            EXECUTE format('REVOKE ALL ON TABLE public.%I FROM anon, authenticated', t);
        END IF;
    END LOOP;
END
$$;
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
    }
};