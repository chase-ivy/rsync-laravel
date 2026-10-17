<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->increments('report_id');
            $table->integer('user_id')->index();
            $table->integer('room_id')->index();
            $table->string('subject', 255);
            $table->text('message');
            $table->enum('status', ['Pending', 'Reviewed', 'Resolved'])->nullable()->default('Pending');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('room_id')->references('room_id')->on('rooms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};