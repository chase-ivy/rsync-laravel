<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account page: profile picture (everyone) and business permit (landlords / sublessors).
 * Three empty columns, nothing deleted. Only the file names are stored; the files are in storage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_photo', 255)->nullable();
            $table->string('business_permit', 255)->nullable();
            $table->timestamp('business_permit_uploaded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['profile_photo', 'business_permit', 'business_permit_uploaded_at']);
        });
    }
};
