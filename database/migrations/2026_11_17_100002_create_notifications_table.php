<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * notifications — Laravel's standard table for in-app ("database") notifications,
 * e.g. "You have a new rental contract" on the tenant dashboard.
 * notifiable_id holds users.user_id (an integer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');   // notifiable_type + notifiable_id (big integer, fits user_id)
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Same protection as 2026_10_16_100010: no access through Supabase's public API.
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
DO $$
BEGIN
    ALTER TABLE public.notifications ENABLE ROW LEVEL SECURITY;
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon')
       AND EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
        REVOKE ALL ON TABLE public.notifications FROM anon, authenticated;
    END IF;
END
$$;
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
