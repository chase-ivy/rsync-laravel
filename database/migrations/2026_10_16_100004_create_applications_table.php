<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** applications — one application per user per room (unique_application). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->increments('application_id');
            $table->integer('user_id');
            $table->integer('room_id')->index();
            $table->enum('status', ['Pending', 'Verified', 'Rejected'])->nullable()->default('Pending')->index();
            $table->timestamp('applied_at')->nullable()->useCurrent();

            $table->unique(['user_id', 'room_id'], 'unique_application');
            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('room_id')->references('room_id')->on('rooms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};