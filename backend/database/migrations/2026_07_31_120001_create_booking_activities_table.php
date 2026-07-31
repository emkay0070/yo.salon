<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('booking_id');
            $table->string('type'); // created, payment_requested, payment_confirmed, confirmed, staff_assigned, completed, cancelled
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('data')->nullable(); // Additional context
            $table->uuid('actor_id')->nullable(); // Who performed the action (user, customer, staff, system)
            $table->string('actor_type')->nullable(); // user, customer, staff, system
            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');

            $table->index('booking_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_activities');
    }
};
