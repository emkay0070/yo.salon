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
        Schema::create('customer_workspace_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            
            $table->uuid('specialist_id');
            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
            
            $table->uuid('target_provider_id');
            $table->foreign('target_provider_id')->references('id')->on('providers')->onDelete('cascade');
            
            $table->uuid('source_provider_id')->nullable();
            $table->foreign('source_provider_id')->references('id')->on('providers')->onDelete('set null');
            
            // Invitation channel: specialist_direct_invite, platform_suggestion, referral, direct_link
            $table->string('channel')->default('specialist_direct_invite');
            
            // Invitation status: sent, opened, accepted, declined, expired
            $table->string('status')->default('sent');
            
            // Timestamps for lifecycle tracking
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            // Additional metadata (optional)
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Indexes for common queries
            $table->index('customer_id');
            $table->index('specialist_id');
            $table->index('target_provider_id');
            $table->index('source_provider_id');
            $table->index('status');
            $table->index('channel');
            $table->index('expires_at');
            
            // Composite index for pending invitations
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_workspace_invitations');
    }
};
