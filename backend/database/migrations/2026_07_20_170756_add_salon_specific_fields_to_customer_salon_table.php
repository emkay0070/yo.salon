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
        Schema::table('customer_salon', function (Blueprint $table) {
            //
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_salon', function (Blueprint $table) {
            $table->dropForeign(['preferred_staff_id']);
            $table->dropColumn(['loyalty_tier', 'wallet_balance', 'preferred_staff_id', 'is_blocked', 'block_reason']);
        });
    }
};
