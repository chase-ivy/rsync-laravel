<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** transactions — text primary key such as "202605-RM01" (not auto-increment). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->string('transaction_id', 50)->primary();
            $table->string('type', 50);
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->enum('status', ['Pending', 'Completed'])->default('Pending');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};