<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 2B.4: Drop salon_id from specialist_notes
 * 
 * SpecialistNote is specialist-global customer knowledge.
 * Canonical fields: specialist_id, customer_id, nullable booking_id.
 * salon_id is not domain authority and must be removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('specialist_notes', function (Blueprint $table) {
            // Drop salon_id only if it exists (fresh migration scenario)
            if (Schema::hasColumn('specialist_notes', 'salon_id')) {
                // Drop foreign key constraint first
                $table->dropForeign(['salon_id']);
                // Drop the index
                $table->dropIndex(['salon_id', 'created_at']);
                // Drop the column
                $table->dropColumn('salon_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('specialist_notes', function (Blueprint $table) {
            $table->uuid('salon_id')->nullable();
            
            // Skip foreign key if salons table doesn't exist
            if (Schema::hasTable('salons')) {
                $table->foreign('salon_id')
                    ->references('id')
                    ->on('salons')
                    ->cascadeOnDelete();
            }
            
            // Add index
            $table->index('salon_id');
        });
    }
};

// Note: Data would need to be restored from context
// This is a structural rollback only
