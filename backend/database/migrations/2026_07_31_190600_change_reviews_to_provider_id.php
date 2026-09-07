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
        // Check if salon_id column exists
        if (Schema::hasColumn('reviews', 'salon_id')) {
            // Add provider_id column
            Schema::table('reviews', function (Blueprint $table) {
                $table->uuid('provider_id')->nullable()->after('salon_id');

                $table->foreign('provider_id')
                    ->references('id')
                    ->on('providers')
                    ->onDelete('cascade');

                $table->index('provider_id');
            });

            // Migrate data: copy salon_id to provider_id via salon provider relationship
            if (DB::getDriverName() !== 'sqlite') {
                DB::statement('
                    UPDATE reviews r
                    SET provider_id = (
                        SELECT provider_id FROM salons WHERE id = r.salon_id
                    )
                    WHERE salon_id IS NOT NULL
                ');
            }

            // Drop salon_id foreign key and column
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropForeign(['salon_id']);
                $table->dropIndex(['salon_id']);
                $table->dropColumn('salon_id');
            });

            // Make provider_id not nullable
            Schema::table('reviews', function (Blueprint $table) {
                $table->uuid('provider_id')->nullable(false)->change();
            });
        } else {
            // Just add provider_id if salon_id doesn't exist
            Schema::table('reviews', function (Blueprint $table) {
                $table->uuid('provider_id')->nullable()->after('id');

                $table->foreign('provider_id')
                    ->references('id')
                    ->on('providers')
                    ->onDelete('cascade');

                $table->index('provider_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add salon_id back
        Schema::table('reviews', function (Blueprint $table) {
            $table->uuid('salon_id')->nullable()->after('id');
            
            $table->foreign('salon_id')
                ->references('id')
                ->on('salons')
                ->onDelete('cascade');
                
            $table->index('salon_id');
        });

        // Migrate data back: copy provider_id to salon_id via provider salon relationship
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('
                UPDATE reviews r
                SET salon_id = (
                    SELECT id FROM salons WHERE provider_id = r.provider_id
                )
                WHERE provider_id IS NOT NULL
            ');
        }

        // Drop provider_id foreign key and column
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['provider_id']);
            $table->dropIndex(['provider_id']);
            $table->dropColumn('provider_id');
        });

        // Make salon_id not nullable
        Schema::table('reviews', function (Blueprint $table) {
            $table->uuid('salon_id')->nullable(false)->change();
        });
    }
};
