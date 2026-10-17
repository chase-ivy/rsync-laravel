<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** rooms — owner_id becomes NULL when the owner is deleted (as in rsync_db.sql). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->increments('room_id');
            $table->integer('owner_id')->nullable()->index();
            $table->string('room_name', 100);
            $table->integer('price');
            $table->text('description')->nullable();
            $table->enum('status', ['Available', 'Occupied'])->nullable()->default('Available');
            $table->integer('max_occupants')->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('owner_id')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};