<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** room_images — deleted together with their room (ON DELETE CASCADE). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_images', function (Blueprint $table) {
            $table->increments('image_id');
            $table->integer('room_id')->index();
            $table->string('filename', 255);
            $table->integer('sort_order')->default(0);

            $table->foreign('room_id')->references('room_id')->on('rooms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_images');
    }
};