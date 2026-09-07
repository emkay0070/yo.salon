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
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('salon_id')->nullable();
            $table->uuid('specialist_id')->nullable();
            $table->string('provider'); // flutterwave, stripe, manual
            $table->string('account_type'); // merchant, subaccount, routing, manual
            $table->string('external_account_id')->nullable(); // Provider's account ID
            $table->string('external_account_reference')->nullable(); // Provider's reference
            $table->string('account_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('verification_status')->default('pending');
            $table->string('settlement_currency')->default('UGX');
            $table->string('settlement_schedule')->default('daily');
            $table->json('metadata')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('salon_id')->references('id')->on('salons')->onDelete('cascade');
            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
            
            $table->index('salon_id');
            $table->index('specialist_id');
            $table->index('provider');
            $table->index('account_type');
            $table->index('is_active');
            $table->index('verification_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};
