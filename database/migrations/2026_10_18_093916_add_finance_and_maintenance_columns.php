<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part G5 — two empty columns, nothing deleted:
 * - transactions.user_id: which tenant a transaction belongs to (report.php shows "Tenant Name").
 *   Old transactions stay without a tenant.
 * - maintenance_requests.resolved_at: when a request was completed (maintainance.php "Date Resolved").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->integer('user_id')->nullable()->index();
            $table->foreign('user_id')->references('user_id')->on('users')->nullOnDelete();
        });

        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->timestamp('resolved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropColumn('resolved_at');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};