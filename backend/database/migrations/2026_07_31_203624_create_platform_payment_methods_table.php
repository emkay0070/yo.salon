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
        Schema::create('platform_payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('type')->unique(); // mtn_personal, airtel_personal, dfcu_bank, cash, mtn_merchant, flutterwave
            $table->string('verification_mode')->default('manual'); // manual, automatic, webhook
            $table->boolean('is_active')->default(true);
            $table->json('details')->nullable(); // Account number, phone number, bank details, etc.
            $table->json('credentials')->nullable(); // Merchant credentials (encrypted)
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index('type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_payment_methods');
    }
};
