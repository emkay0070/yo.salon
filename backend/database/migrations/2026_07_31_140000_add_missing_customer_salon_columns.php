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
            if (!Schema::hasColumn('customer_salon', 'loyalty_tier')) {
                $table->string('loyalty_tier')->default('bronze')->after('joined_at');
            }
            if (!Schema::hasColumn('customer_salon', 'wallet_balance')) {
                $table->decimal('wallet_balance', 10, 2)->default(0)->after('loyalty_tier');
            }
            if (!Schema::hasColumn('customer_salon', 'preferred_staff_id')) {
                $table->uuid('preferred_staff_id')->nullable()->after('wallet_balance');
            }
            if (!Schema::hasColumn('customer_salon', 'is_blocked')) {
                $table->boolean('is_blocked')->default(false)->after('preferred_staff_id');
            }
            if (!Schema::hasColumn('customer_salon', 'block_reason')) {
                $table->text('block_reason')->nullable()->after('is_blocked');
            }

            if (Schema::hasColumn('customer_salon', 'preferred_staff_id') && !Schema::hasColumn('customer_salon', 'preferred_staff_id_foreign')) {
                $table->foreign('preferred_staff_id')->references('id')->on('staff')->onDelete('set null');
            }
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
