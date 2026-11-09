<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registration now asks only for email, password and role. Name and birthday are filled in later
 * on the Account page, so name and age may be empty for new accounts. Existing rows keep their values.
 * Also adds the tenant details: contact number, emergency contact, social media link and a valid ID.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN name DROP NOT NULL');
            DB::statement('ALTER TABLE users ALTER COLUMN age DROP NOT NULL');
        } else {
            // SQLite (tests) rebuilds the table to change a column and can't copy the LOWER(email) index.
            if ($driver === 'sqlite') {
                DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');
            }
            Schema::table('users', function (Blueprint $table) {
                $table->string('name', 100)->nullable()->change();
                $table->integer('age')->nullable()->change();
            });
            if ($driver === 'sqlite') {
                DB::statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (LOWER(email))');
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->date('birthday')->nullable();
            $table->string('contact_number', 20)->nullable();
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_relationship', 30)->nullable();
            $table->string('emergency_contact_number', 20)->nullable();
            $table->string('social_media_url', 255)->nullable();
            $table->string('valid_id', 255)->nullable();
            $table->timestamp('valid_id_uploaded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'birthday', 'contact_number', 'emergency_contact_name', 'emergency_contact_relationship',
                'emergency_contact_number', 'social_media_url', 'valid_id', 'valid_id_uploaded_at',
            ]);
        });
        // name and age stay optional, so accounts created in the meantime never break.
    }
};
