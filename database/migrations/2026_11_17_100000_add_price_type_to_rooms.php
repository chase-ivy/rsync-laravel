<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How the room price is meant:
 * - per_person: each tenant pays the price (the default, also for every existing room);
 * - whole_room: the price is for the whole room and is split between the tenants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('price_type', 20)->default('per_person');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('price_type');
        });
    }
};
