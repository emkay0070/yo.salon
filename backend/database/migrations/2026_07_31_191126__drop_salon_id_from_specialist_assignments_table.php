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
        Schema::table('specialist_assignments', function (Blueprint $table) {
            // Drop salon_id only if it exists (fresh migration scenario)
            if (Schema::hasColumn('specialist_assignments', 'salon_id')) {
                $table->dropUnique('uq_specialist_salon_assignment');
                $table->dropIndex(['salon_id', 'status']);
                $table->dropForeign(['salon_id']);
                $table->dropColumn('salon_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('specialist_assignments', function (Blueprint $table) {
            $table->uuid('salon_id')->nullable();
            
            // Skip foreign key if salons table doesn't exist
            if (Schema::hasTable('salons')) {
                $table->foreign('salon_id')->references('id')->on('salons')->onDelete('cascade');
            }
        });
    }
};
