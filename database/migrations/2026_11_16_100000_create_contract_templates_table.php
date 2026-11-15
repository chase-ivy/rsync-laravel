<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * contract_templates — each landlord / sublessor's own contract format (repairs rule, deposit,
 * advance, utilities note, terms and house rules). At most one row per landlord.
 * New contracts copy these values when the tenant is assigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unique();
            $table->string('repairs_policy', 20)->default('landlord');   // landlord / tenant / shared
            $table->decimal('deposit', 10, 2)->nullable();              // security deposit in pesos
            $table->unsignedSmallInteger('advance_months')->nullable(); // months paid in advance (0–12)
            $table->string('utilities_note', 255)->nullable();
            $table->json('terms')->nullable();                          // list of lines
            $table->json('house_rules')->nullable();                    // list of lines
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
        });

        // Same protection as 2026_10_16_100010: no access through Supabase's public API.
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
DO $$
BEGIN
    ALTER TABLE public.contract_templates ENABLE ROW LEVEL SECURITY;
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon')
       AND EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
        REVOKE ALL ON TABLE public.contract_templates FROM anon, authenticated;
    END IF;
END
$$;
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_templates');
    }
};
