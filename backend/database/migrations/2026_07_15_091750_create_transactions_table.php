<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('salon_id');
            $table->uuid('booking_id')->nullable();
            $table->uuid('customer_id')->nullable();
            $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('status')->default('initiated');
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('UGX');
            $table->string('internal_reference')->unique();
            $table->string('provider_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('salon_id')->references('id')->on('salons')->onDelete('cascade');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('set null');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
