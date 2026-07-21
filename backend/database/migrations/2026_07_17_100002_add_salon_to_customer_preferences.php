<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_preferences', function (Blueprint $table) {
            // Add salon_id to scope preferences per customer-salon pair
            if (!Schema::hasColumn('customer_preferences', 'salon_id')) {
                $table->uuid('salon_id')->nullable()->after('customer_id');
                $table->foreign('salon_id')->references('id')->on('salons')->onDelete('cascade');
            }
        });

        // Handle unique constraint change outside of table closure for SQLite compatibility
        // First, drop the old unique constraint if it exists
        try {
            DB::transaction(function () {
                Schema::table('customer_preferences', function (Blueprint $table) {
                    $table->dropUnique(['customer_id']);
                });
            });
        } catch (\Exception $e) {
            // Index may not exist, continue
        }

        // Add the new composite unique constraint
        Schema::table('customer_preferences', function (Blueprint $table) {
            $table->unique(['customer_id', 'salon_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_preferences', function (Blueprint $table) {
            $table->dropUnique(['customer_id', 'salon_id']);
        });

        // Add back the old unique constraint
        try {
            DB::transaction(function () {
                Schema::table('customer_preferences', function (Blueprint $table) {
                    $table->unique(['customer_id']);
                });
            });
        } catch (\Exception $e) {
            // Index may already exist, continue
        }

        Schema::table('customer_preferences', function (Blueprint $table) {
            $table->dropForeign(['salon_id']);
            $table->dropColumn('salon_id');
        });
    }
};
