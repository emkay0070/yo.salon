<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add phone number for STK push
        Schema::table('payment_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_requests', 'phone_number')) {
                $table->string('phone_number')->nullable()->after('customer_id');
            }
        });

        // Add provider name for multi-provider support
        Schema::table('payment_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_requests', 'provider')) {
                $table->string('provider')->nullable()->after('payment_method_id');
            }
        });

        // For PostgreSQL, use a check constraint instead of enum for status
        // This is more compatible and easier to modify
        DB::statement("ALTER TABLE payment_requests ADD CONSTRAINT check_payment_requests_status CHECK (status IN ('pending', 'processing', 'successful', 'failed', 'cancelled', 'expired'))");
    }

    public function down(): void
    {
        // Drop the check constraint
        DB::statement("ALTER TABLE payment_requests DROP CONSTRAINT check_payment_requests_status");
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dropColumn('phone_number');
            $table->dropColumn('provider');
        });
    }
};
