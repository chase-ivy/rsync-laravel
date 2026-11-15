<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant's "I agree" on their contract: when they accepted it and from which IP address.
 * Empty = not accepted yet (or the landlord changed the contract after it was accepted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable();
            $table->string('accepted_ip', 45)->nullable();   // 45 characters fits an IPv6 address
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'accepted_ip']);
        });
    }
};
