<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/** users — mirrors rsync_db.sql + email_verified_at (approved feature F31). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('user_id');
            $table->string('name', 100);
            $table->integer('age');
            $table->string('email', 100);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->enum('role', ['tenant', 'landlord', 'sublessor'])->default('tenant');
            $table->boolean('verified')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        // MariaDB compared emails case-insensitively (utf8mb4_general_ci); PostgreSQL does not.
        // This unique index keeps "Kobe@gmail.com" and "kobe@gmail.com" as the same account.
        DB::statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (LOWER(email))');
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};