<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5: Remove Staff.availability field
 * 
 * This migration removes the availability field from the staff table
 * as it has been replaced by the AssignmentSchedule system in the new architecture.
 * 
 * The new Availability Engine uses:
 * - ProviderSchedule (provider-level hours)
 * - SalonSchedule (salon-level hours) 
 * - AssignmentSchedule (specialist assignment-level hours)
 * 
 * Staff.availability is legacy and no longer used in the Specialist-first architecture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('availability');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->json('availability')->nullable();
        });
    }
};
