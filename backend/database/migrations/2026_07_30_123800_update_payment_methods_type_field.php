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
        Schema::table('payment_methods', function (Blueprint $table) {
            // For PostgreSQL, we need to drop the column and recreate it with a check constraint
            // First, drop the existing type column
            $table->dropColumn('type');
            
            // Then add it back with a check constraint
            $table->string('type')->default('api');
        });
        
        // Add check constraint using raw SQL for PostgreSQL
        DB::statement("ALTER TABLE payment_methods ADD CONSTRAINT payment_methods_type_check CHECK (type IN ('api', 'manual', 'offline', 'gateway'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the check constraint
        DB::statement("ALTER TABLE payment_methods DROP CONSTRAINT payment_methods_type_check");
        
        Schema::table('payment_methods', function (Blueprint $table) {
            // Revert back to simple string without constraint
            $table->dropColumn('type');
            $table->string('type')->default('api');
        });
    }
};
