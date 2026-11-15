<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rooms get a written address ("12 Lower Magsaysay Ave, Brgy. Lourdes") and a list of amenities
 * (saved as a JSON list of keys like ["wifi","own_cr"], see Room::AMENITIES).
 * Both are empty for older rooms. Nothing existing is changed or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('address', 255)->nullable();
            $table->json('amenities')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['address', 'amenities']);
        });
    }
};
