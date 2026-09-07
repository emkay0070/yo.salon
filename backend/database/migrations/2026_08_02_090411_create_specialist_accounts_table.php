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
        Schema::create('specialist_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('specialist_id')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->jsonb('preferences')->nullable();
            $table->rememberToken();
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
            
            // Indexes
            $table->index(['email']);
            $table->index(['specialist_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specialist_accounts');
    }
};
