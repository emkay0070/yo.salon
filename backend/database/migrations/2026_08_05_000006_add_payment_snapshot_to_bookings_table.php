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
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_strategy')->nullable()->after('status');
            $table->boolean('deposit_required')->default(false)->after('payment_strategy');
            $table->unsignedBigInteger('amount_due')->nullable()->after('deposit_required');
            $table->unsignedBigInteger('amount_paid')->default(0)->after('amount_due');
            $table->unsignedBigInteger('amount_remaining')->nullable()->after('amount_paid');
            $table->timestamp('payment_expires_at')->nullable()->after('payment_status');
            $table->json('payment_snapshot')->nullable()->after('payment_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'payment_strategy',
                'deposit_required',
                'amount_due',
                'amount_paid',
                'amount_remaining',
                'payment_expires_at',
                'payment_snapshot',
            ]);
        });
    }
};
