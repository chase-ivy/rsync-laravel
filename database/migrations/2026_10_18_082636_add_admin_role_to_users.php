<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Part G3 — adds the "admin" role and a "rejected" date for the admin's Verify Users list.
 * Safe: no rows are changed or deleted. It only widens the allowed role values and adds an empty column.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // On PostgreSQL an enum column is a text column with a CHECK rule; replace the rule.
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('tenant', 'landlord', 'sublessor', 'admin'))");
        } else {
            // SQLite (used by the tests) rebuilds the table to change a column, and can't copy the
            // LOWER(email) index while doing it — so take that index off and put it back afterwards.
            if ($driver === 'sqlite') {
                DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');
            }
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['tenant', 'landlord', 'sublessor', 'admin'])->default('tenant')->change();
            });
            if ($driver === 'sqlite') {
                DB::statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (LOWER(email))');
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('verification_rejected_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('verification_rejected_at');
        });
        // The role rule is left as it is, so existing admin accounts never break.
    }
};