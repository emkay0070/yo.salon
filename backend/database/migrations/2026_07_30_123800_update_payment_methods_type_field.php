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
        
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE payment_methods ADD CONSTRAINT payment_methods_type_check CHECK (type IN ('api', 'manual', 'offline', 'gateway', 'mobile_money', 'card', 'cash'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE payment_methods DROP CONSTRAINT payment_methods_type_check");
        }
        
        Schema::table('payment_methods', function (Blueprint $table) {
            // Revert back to simple string without constraint
            $table->dropColumn('type');
            $table->string('type')->default('api');
        });
    }
};
