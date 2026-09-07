<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type'); // sms, email, push
            $table->string('recipient'); // phone number or email
            $table->text('subject')->nullable(); // for emails
            $table->text('message');
            $table->json('data')->nullable(); // additional data
            $table->string('status')->default('pending'); // pending, queued, sent, failed
            $table->integer('attempts')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->string('related_type')->nullable(); // booking, customer, etc.
            $table->uuid('related_id')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'scheduled_at']);
            $table->index(['type', 'status']);
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_jobs');
    }
};
