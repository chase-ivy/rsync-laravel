<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** tenants — one row per tenancy (contract), not per person. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->increments('tenant_id');
            $table->integer('user_id')->index();
            $table->integer('room_id')->index();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['Active', 'Left'])->nullable()->default('Active');

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('room_id')->references('room_id')->on('rooms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};