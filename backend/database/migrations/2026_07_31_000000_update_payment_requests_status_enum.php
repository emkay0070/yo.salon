<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            // Change status to enum for better data integrity
            $table->enum('status', ['pending', 'processing', 'successful', 'failed', 'cancelled', 'expired'])
                  ->default('pending')
                  ->change();
            
            // Add phone number for STK push
            $table->string('phone_number')->nullable()->after('customer_id');
            
            // Add provider name for multi-provider support
            $table->string('provider')->nullable()->after('payment_method_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
            $table->dropColumn('phone_number');
            $table->dropColumn('provider');
        });
    }
};
