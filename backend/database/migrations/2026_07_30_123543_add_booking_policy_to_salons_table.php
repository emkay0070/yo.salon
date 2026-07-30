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
        Schema::table('salons', function (Blueprint $table) {
            $table->boolean('booking_deposit_enabled')->default(false);
            $table->enum('deposit_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('deposit_value', 10, 2)->nullable();
            $table->enum('deposit_required_for', ['never', 'all', 'first_time', 'high_value'])->default('all');
            $table->integer('deposit_min_service_amount')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->dropColumn([
                'booking_deposit_enabled',
                'deposit_type',
                'deposit_value',
                'deposit_required_for',
                'deposit_min_service_amount',
            ]);
        });
    }
};
