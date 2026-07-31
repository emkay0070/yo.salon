<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('salon_id')->nullable();
            $table->bigInteger('user_id')->nullable(); // For salon owners/staff
            $table->uuid('customer_id')->nullable(); // For customers
            $table->uuid('staff_id')->nullable(); // For staff notifications
            $table->string('type'); // payment_confirmed, booking_created, etc.
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional context (booking_id, amount, etc.)
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('salon_id')->references('id')->on('salons')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('cascade');

            $table->index(['salon_id', 'read_at']);
            $table->index(['user_id', 'read_at']);
            $table->index(['customer_id', 'read_at']);
            $table->index(['staff_id', 'read_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
