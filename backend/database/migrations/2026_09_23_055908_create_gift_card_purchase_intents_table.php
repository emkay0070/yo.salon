<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_card_purchase_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('provider_id');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('UGX');
            $table->string('status', 30)->default('pending'); // pending, paid, failed
            $table->string('payment_method')->nullable();
            $table->string('provider_reference')->nullable();
            $table->text('message')->nullable();
            $table->uuid('gift_card_id')->nullable(); // Set upon issuance
            
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('provider_id')->references('id')->on('providers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_purchase_intents');
    }
};
