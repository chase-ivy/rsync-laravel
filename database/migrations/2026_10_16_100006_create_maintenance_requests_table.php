<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** maintenance_requests — tenant_id points to users.user_id (as in rsync_db.sql). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->increments('request_id');
            $table->integer('tenant_id')->index();
            $table->integer('room_id')->index();
            $table->text('description');
            $table->enum('status', ['Pending', 'In Progress', 'Completed'])->default('Pending');
            $table->timestamp('submitted_at')->useCurrent();

            $table->foreign('tenant_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('room_id')->references('room_id')->on('rooms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_requests');
    }
};