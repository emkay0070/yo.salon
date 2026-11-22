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
        Schema::create('verification_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('identifier');            // E.164 phone number or email address
            $table->string('identifier_type', 20);   // 'phone' or 'email'
            $table->string('purpose', 50);           // 'phone_verification', 'registration', etc.
            $table->string('channel', 30);           // 'whatsapp', 'email', 'sms_africas_talking', etc.
            $table->string('code_hash');             // SHA-256 hash of OTP (plaintext is never stored)
            $table->timestamp('expires_at');
            $table->timestamp('resend_available_at');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->timestamp('consumed_at')->nullable();
            $table->json('metadata')->nullable();    // Country, IP, user-agent, etc.
            $table->string('delivery_status', 30)->default('pending'); // 'delivered', 'failed', 'paused', 'mocked'
            $table->text('delivery_error')->nullable();
            $table->timestamps();

            $table->index(['identifier', 'purpose', 'consumed_at']);
            $table->index(['expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_challenges');
    }
};
