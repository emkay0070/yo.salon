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
        DB::transaction(function () {
            // Step 1: Add new columns as nullable first
            Schema::table('customer_specialist', function (Blueprint $table) {
                $table->uuid('provider_id')->nullable()->after('specialist_id');
                $table->foreign('provider_id')->references('id')->on('providers')->onDelete('cascade');
                
                $table->string('relationship_origin')->default('salon')->after('provider_id');
                $table->string('acquisition_source')->default('booking')->after('relationship_origin');
                $table->uuid('source_provider_id')->nullable()->after('acquisition_source');
                $table->foreign('source_provider_id')->references('id')->on('providers')->onDelete('set null');
            });

            // Step 2 & 3: Backfill data and handle duplicates (skip for SQLite tests since tables are empty)
            if (DB::getDriverName() !== 'sqlite') {
                DB::statement('
                    UPDATE customer_specialist cs
                    SET provider_id = b.provider_id
                    FROM bookings b
                    WHERE cs.first_booking_id = b.id
                    AND cs.provider_id IS NULL
                ');

                // Step 3: Handle potential duplicates by creating separate provider-specific rows
                $duplicates = DB::select('
                    SELECT customer_id, specialist_id, COUNT(*) as count
                    FROM customer_specialist
                    WHERE provider_id IS NOT NULL
                    GROUP BY customer_id, specialist_id
                    HAVING COUNT(*) > 1
                ');

                if (!empty($duplicates)) {
                    foreach ($duplicates as $duplicate) {
                        DB::statement('
                            DELETE FROM customer_specialist
                            WHERE customer_id = ? AND specialist_id = ?
                            AND id NOT IN (
                                SELECT id FROM (
                                    SELECT id FROM customer_specialist
                                    WHERE customer_id = ? AND specialist_id = ?
                                    ORDER BY created_at ASC
                                    LIMIT 1
                                ) as keep
                            )
                        ', [$duplicate->customer_id, $duplicate->specialist_id, $duplicate->customer_id, $duplicate->specialist_id]);
                    }
                }
            }

            // Step 4: Drop existing primary key
            Schema::table('customer_specialist', function (Blueprint $table) {
                $table->dropPrimary(['customer_id', 'specialist_id']);
            });

            // Step 5: Add new composite primary key
            Schema::table('customer_specialist', function (Blueprint $table) {
                $table->primary(['customer_id', 'specialist_id', 'provider_id']);
            });

            // Step 6: Make provider_id NOT NULL (now that it's part of PK and backfilled)
            Schema::table('customer_specialist', function (Blueprint $table) {
                $table->uuid('provider_id')->nullable(false)->change();
            });

            // Step 7: Add indexes for performance
            Schema::table('customer_specialist', function (Blueprint $table) {
                $table->index('provider_id');
                $table->index('relationship_origin');
                $table->index('acquisition_source');
                $table->index('source_provider_id');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function () {
            // Step 1: Drop new primary key
            Schema::table('customer_specialist', function (Blueprint $table) {
                $table->dropPrimary(['customer_id', 'specialist_id', 'provider_id']);
            });

            // Step 2: Restore original primary key
            Schema::table('customer_specialist', function (Blueprint $table) {
                $table->primary(['customer_id', 'specialist_id']);
            });

            // Step 3: Drop new columns and indexes
            Schema::table('customer_specialist', function (Blueprint $table) {
                $table->dropForeign(['provider_id']);
                $table->dropForeign(['source_provider_id']);
                $table->dropIndex(['provider_id']);
                $table->dropIndex(['relationship_origin']);
                $table->dropIndex(['acquisition_source']);
                $table->dropIndex(['source_provider_id']);
                
                $table->dropColumn('provider_id');
                $table->dropColumn('relationship_origin');
                $table->dropColumn('acquisition_source');
                $table->dropColumn('source_provider_id');
            });
        });
    }
};
