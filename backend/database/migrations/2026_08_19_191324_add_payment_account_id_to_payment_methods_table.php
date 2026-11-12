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
        Schema::table('payment_methods', function (Blueprint $table) {
            // Add payment_account_id after provider_id (salon_id no longer exists in fresh migrations)
            $table->foreignId('payment_account_id')->nullable()->after('provider_id')->constrained()->nullOnDelete();
            $table->index('payment_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropForeign(['payment_account_id']);
            $table->dropIndex(['payment_account_id']);
            $table->dropColumn('payment_account_id');
        });
    }
};
