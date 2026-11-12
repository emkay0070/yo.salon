<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove role, specializations, and availability from Staff table
 *
 * Architecture cleanup:
 * - Staff.role should not exist - authority belongs in ProviderUser.role
 * - Staff.specializations should not exist - professional identity belongs in Specialist
 * - Staff.availability should not exist - availability belongs to AssignmentSchedule system
 *
 * Staff should only contain provider-local operational data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'role')) {
                $table->dropColumn('role');
            }
            if (Schema::hasColumn('staff', 'specializations')) {
                $table->dropColumn('specializations');
            }
            if (Schema::hasColumn('staff', 'availability')) {
                $table->dropColumn('availability');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (!Schema::hasColumn('staff', 'role')) {
                $table->string('role')->default('staff')->after('email');
            }
            if (!Schema::hasColumn('staff', 'specializations')) {
                $table->json('specializations')->nullable()->after('email');
            }
            if (!Schema::hasColumn('staff', 'availability')) {
                $table->json('availability')->nullable()->after('email');
            }
        });
    }
};
