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
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->decimal('balance', 10, 2)->after('amount')->default(0);
            $table->enum('status', ['created', 'issued', 'available', 'partially_redeemed', 'fully_redeemed', 'expired', 'cancelled'])->after('active')->default('created');
            $table->timestamp('cancelled_at')->nullable()->after('expires_at');
            $table->timestamp('issued_at')->nullable()->after('created_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->dropColumn(['balance', 'status', 'cancelled_at', 'issued_at']);
        });
    }
};
