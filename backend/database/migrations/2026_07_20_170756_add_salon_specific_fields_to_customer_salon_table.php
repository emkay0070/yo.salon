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
            $table->string('loyalty_tier')->default('bronze')->after('joined_at');
            $table->decimal('wallet_balance', 10, 2)->default(0)->after('loyalty_tier');
            $table->uuid('preferred_staff_id')->nullable()->after('wallet_balance');
            $table->boolean('is_blocked')->default(false)->after('preferred_staff_id');
            $table->text('block_reason')->nullable()->after('is_blocked');

            $table->foreign('preferred_staff_id')->references('id')->on('staff')->onDelete('set null');
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
