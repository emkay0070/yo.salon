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
        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('amount', 'gross_amount');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('gateway_fee', 12, 2)->default(0)->after('gross_amount');
            $table->decimal('platform_fee', 12, 2)->default(0)->after('gateway_fee');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('platform_fee');
            $table->decimal('net_amount', 12, 2)->default(0)->after('tax_amount');
            $table->foreignId('settlement_id')->nullable()->after('status')->constrained('settlements')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['settlement_id']);
            $table->dropColumn(['settlement_id', 'gateway_fee', 'platform_fee', 'tax_amount', 'net_amount']);
            $table->renameColumn('gross_amount', 'amount');
        });
    }
};
