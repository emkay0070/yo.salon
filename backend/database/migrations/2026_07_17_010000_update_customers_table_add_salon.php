<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Only add salon_id if it doesn't exist
            if (!Schema::hasColumn('customers', 'salon_id')) {
                $table->uuid('salon_id')->nullable()->after('id');
                $table->foreign('salon_id')->references('id')->on('salons')->onDelete('cascade');
            }
            // Only make phone unique if it's not already unique
            if (!Schema::hasColumn('customers', 'phone')) {
                $table->string('phone')->unique()->change();
            }
            // Make email nullable
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['salon_id']);
            $table->dropColumn('salon_id');
            $table->dropUnique(['phone']);
        });
    }
};
