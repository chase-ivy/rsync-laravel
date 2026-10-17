<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->increments('payment_id');
            $table->integer('user_id')->index();
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->enum('status', ['Pending', 'Verified'])->default('Pending');

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};