<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('booking_id');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            
            // Link to the reminder rule that generated this instance
            $table->uuid('reminder_rule_id')->nullable();
            
            // Recipient information
            $table->uuid('recipient_customer_id')->nullable();
            $table->foreign('recipient_customer_id')->references('id')->on('customers')->onDelete('set null');
            
            // Reminder type and channel
            $table->string('type'); // appointment_reminder, etc.
            $table->string('channel'); // sms, email, in_app
            
            // Scheduling
            $table->timestamp('scheduled_for');
            $table->timestamp('scheduled_at')->nullable(); // When the NotificationJob was created
            
            // Delivery tracking
            $table->uuid('notification_job_id')->nullable();
            $table->foreign('notification_job_id')->references('id')->on('notification_jobs')->onDelete('set null');
            
            // Status lifecycle
            $table->string('status')->default('pending'); // pending, scheduled, sent, failed, cancelled
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            // Retry tracking
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
            
            // Metadata
            $table->json('data')->nullable(); // Additional context
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index(['status', 'scheduled_for']);
            $table->index(['booking_id', 'status']);
            $table->index(['notification_job_id']);
            $table->index(['scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_reminders');
    }
};
