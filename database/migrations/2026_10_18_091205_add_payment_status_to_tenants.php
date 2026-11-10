<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part G4 — "Update Tenant Status" from tenants.php needs somewhere to keep
 * Registered / Paid / On-Time / Delayed. Adds one column; existing contracts start as "Registered".
 * Nothing is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('Registered');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });
    }
};