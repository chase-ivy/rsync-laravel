<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A copy ("snapshot") of the contract terms saved on each contract when the tenant is assigned,
 * so a later change of the room price or the landlord's template doesn't change old contracts.
 * All empty for older contracts; those fall back to the landlord's current template.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('monthly_rent', 10, 2)->nullable();
            $table->string('repairs_policy', 20)->nullable();
            $table->decimal('deposit', 10, 2)->nullable();
            $table->unsignedSmallInteger('advance_months')->nullable();
            $table->string('utilities_note', 255)->nullable();
            $table->json('terms')->nullable();
            $table->json('house_rules')->nullable();
            $table->timestamp('terms_updated_at')->nullable();   // when the landlord last edited this contract
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['monthly_rent', 'repairs_policy', 'deposit', 'advance_months', 'utilities_note', 'terms', 'house_rules', 'terms_updated_at']);
        });
    }
};
