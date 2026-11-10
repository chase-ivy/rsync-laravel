<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** password_resets — RSYNC's own OTP table (not Laravel's password_reset_tokens). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_resets', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email', 100)->index();
            $table->string('otp', 6);
            $table->dateTime('expires_at');
            $table->boolean('used')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_resets');
    }
};