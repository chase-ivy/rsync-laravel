<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * More verification documents for landlords and sublessors (next to the existing business_permit):
 * Fire Safety Inspection Certificate, Sanitary Permit and Barangay Business Clearance.
 * Only the private file name and the upload time are stored. Empty columns, nothing deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('fire_safety_certificate', 255)->nullable();
            $table->timestamp('fire_safety_certificate_uploaded_at')->nullable();
            $table->string('sanitary_permit', 255)->nullable();
            $table->timestamp('sanitary_permit_uploaded_at')->nullable();
            $table->string('barangay_clearance', 255)->nullable();
            $table->timestamp('barangay_clearance_uploaded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'fire_safety_certificate', 'fire_safety_certificate_uploaded_at',
                'sanitary_permit', 'sanitary_permit_uploaded_at',
                'barangay_clearance', 'barangay_clearance_uploaded_at',
            ]);
        });
    }
};
